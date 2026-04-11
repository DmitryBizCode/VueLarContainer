<?php

namespace App\Services;

use App\DataTransferObjects\ActuatorInputDto;
use App\DataTransferObjects\SimulationStateDto;
use App\Models\Container;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Rental;
use App\Services\Metrics\TelemetryWriteBuffer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimulationService
{
    /**
     * @param  bool  $writeMetricsToDatabaseImmediately  When true (e.g. 3D / actuator API), INSERT raw samples into `metrics` immediately instead of the Redis buffer.
     */
    public function tickContainer(
        Container $container,
        ?Rental $rental = null,
        ?ActuatorInputDto $actuators = null,
        bool $writeMetricsToDatabaseImmediately = false
    ): SimulationStateDto {
        $snapshot = ContainerSimulationSnapshot::query()->firstOrNew(['container_id' => $container->id]);

        if ($rental === null) {
            $rental = $this->resolveRentalForBackgroundTick($container, $snapshot);
        }

        if ($rental === null) {
            return $this->loadState($snapshot);
        }

        $actuators ??= ActuatorInputDto::fromArray($snapshot->actuators ?? []);

        $state = $this->loadState($snapshot);
        $targets = $this->resolveTargets($rental, $actuators);

        $state = $this->applyConvergence($state, $targets);

        $tBeforeAc = $state->temperature_c;
        $state = $this->applyAc($state, $actuators);
        $state = $this->applyInverseTemperatureHumidityRelation($state, $tBeforeAc);

        $state = $this->processInterdependencies($state, $actuators);

        $state = $this->applyGaussianNoise($state);

        $state = $this->clampState($state);

        $this->persist($container, $rental, $state, $actuators, $snapshot, $writeMetricsToDatabaseImmediately);

        return $state;
    }

    /**
     * Advance simulation one tick for every IoT-active container (cursor-based, memory-efficient).
     *
     * @return int Number of containers processed
     */
    public function tickAllIotActiveContainers(?int $onlyContainerId = null): int
    {
        $query = Container::query()->where('iot_active', true);

        if ($onlyContainerId !== null) {
            $query->where('id', $onlyContainerId);
        }

        $count = 0;
        foreach ($query->cursor() as $container) {
            $snapshot = ContainerSimulationSnapshot::query()->firstOrNew(['container_id' => $container->id]);
            $rental = $this->resolveRentalForBackgroundTick($container, $snapshot);
            if ($rental === null) {
                continue;
            }
            if (! $rental->is_telemetry_active) {
                continue;
            }
            $this->tickContainer($container, $rental);
            $count++;
        }

        return $count;
    }

    public function processInterdependencies(SimulationStateDto $state, ActuatorInputDto $actuators): SimulationStateDto
    {
        $cfg = config('simulation');
        $ambient = $cfg['ambient'];
        $vent = $cfg['ventilation'];
        $hum = $cfg['humidifier'];
        $cargo = $cfg['cargo_respiration'];
        $lights = $cfg['lights'];
        $pump = $cfg['pump'];
        $doorCfg = $cfg['door'] ?? ['exchange_efficiency' => 0.25];
        $waterCfg = $cfg['water_level'] ?? ['recovery_per_tick' => 0.8, 'condensation_humidity_factor' => 0.02];

        $state = $state->with([
            SimulationStateDto::SENSOR_DOOR_OPEN => $actuators->doorOpen ? 1.0 : 0.0,
            SimulationStateDto::SENSOR_VENTILATION_ON => $actuators->ventilation ? 1.0 : 0.0,
        ]);

        if ($actuators->doorOpen) {
            $eff = $doorCfg['exchange_efficiency'] ?? 0.25;
            $state = $state->with([
                SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c
                    + ($ambient['temperature_c'] - $state->temperature_c) * $eff,
                SimulationStateDto::SENSOR_HUMIDITY => $state->humidity_rh
                    + ($ambient['humidity_rh'] - $state->humidity_rh) * $eff,
                SimulationStateDto::SENSOR_CO2 => max(
                    $cfg['clamps']['co2_min'],
                    $state->co2_ppm + ($ambient['co2_ppm'] - $state->co2_ppm) * $eff
                ),
            ]);
        }

        if ($actuators->humidifier) {
            $state = $state->with([
                SimulationStateDto::SENSOR_HUMIDITY => min(
                    $cfg['clamps']['humidity_max'],
                    $state->humidity_rh + $hum['delta_humidity_rh']
                ),
                SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c + $hum['delta_temperature_c'],
            ]);
        }

        $freshenerCfg = $cfg['freshener'] ?? ['delta_humidity_rh' => 1.15];
        if ($actuators->freshenerOn) {
            $state = $state->with([
                SimulationStateDto::SENSOR_HUMIDITY => min(
                    $cfg['clamps']['humidity_max'],
                    $state->humidity_rh + ($freshenerCfg['delta_humidity_rh'] ?? 1.15)
                ),
            ]);
        }

        if ($actuators->ventilation) {
            $eff = $vent['pull_efficiency'];
            $state = $state->with([
                SimulationStateDto::SENSOR_CO2 => max(
                    $cfg['clamps']['co2_min'],
                    $state->co2_ppm - $vent['co2_drop_per_tick']
                ),
                SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c
                    + ($ambient['temperature_c'] - $state->temperature_c) * $eff,
                SimulationStateDto::SENSOR_HUMIDITY => $state->humidity_rh
                    + ($ambient['humidity_rh'] - $state->humidity_rh) * $eff,
            ]);
        }

        if ($actuators->heater) {
            $state = $state->with([
                SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c + $cfg['heater']['delta_temperature_c'],
            ]);
        }

        if ($actuators->mainLight || $actuators->irLamp) {
            $mult = ($actuators->mainLight && $actuators->irLamp) ? 2 : 1;
            $state = $state->with([
                SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c + $lights['delta_temperature_c'] * $mult,
                SimulationStateDto::SENSOR_HUMIDITY => max(
                    $cfg['clamps']['humidity_min'],
                    $state->humidity_rh + $lights['delta_humidity_rh'] * $mult
                ),
            ]);
        }

        $condCfg = $cfg['condensation'] ?? ['rh_threshold' => 68.0, 'water_gain_per_tick' => 0.55];
        $rhTh = (float) ($condCfg['rh_threshold'] ?? 68.0);
        $condGain = (float) ($condCfg['water_gain_per_tick'] ?? 0.55);
        $waterLevel = $state->water_level_pct;
        if (($actuators->humidifier || $actuators->freshenerOn) && $state->humidity_rh > $rhTh) {
            $waterLevel += $condGain * ($state->humidity_rh - $rhTh) / max(1.0, 100.0 - $rhTh);
        }
        $waterLevel = min(100.0, $waterLevel);

        $autoTh = (float) (($cfg['auto_pump']['threshold_pct'] ?? 72.0));
        $effectivePump = $actuators->pump || ($waterLevel >= $autoTh);

        if ($effectivePump) {
            $drop = $pump['water_level_drop_per_tick'] ?? 3.0;
            $waterLevel = max(0, $waterLevel - $drop);
        } else {
            $recovery = $waterCfg['recovery_per_tick'] ?? 0.8;
            $waterLevel = min(100, $waterLevel + $recovery);
        }

        $state = $state->with([
            SimulationStateDto::SENSOR_WATER_LEVEL => $waterLevel,
            SimulationStateDto::SENSOR_PUMP_RUNNING => $effectivePump ? 1.0 : 0.0,
        ]);

        if ($waterLevel > 50) {
            $cond = $waterCfg['condensation_humidity_factor'] ?? 0.02;
            $state = $state->with([
                SimulationStateDto::SENSOR_HUMIDITY => min(
                    $cfg['clamps']['humidity_max'],
                    $state->humidity_rh + $cond * ($waterLevel - 50) / 50
                ),
            ]);
        }

        if ($effectivePump) {
            $state = $state->with([
                SimulationStateDto::SENSOR_HUMIDITY => max(
                    $cfg['clamps']['humidity_min'],
                    $state->humidity_rh + $pump['delta_humidity_rh']
                ),
            ]);
        }

        $actuatorLoad = (int) $actuators->acStatus
            + (int) $actuators->humidifier
            + (int) $actuators->ventilation
            + (int) $actuators->heater;
        $cargoScale = max(0.25, 1.0 - $actuatorLoad * 0.15);

        return $state->with([
            SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c + $cargo['delta_temperature_c'] * $cargoScale,
            SimulationStateDto::SENSOR_HUMIDITY => $state->humidity_rh + $cargo['delta_humidity_rh'] * $cargoScale,
            SimulationStateDto::SENSOR_CO2 => $state->co2_ppm + $cargo['delta_co2_ppm'] * $cargoScale,
        ]);
    }

    protected function applyInverseTemperatureHumidityRelation(SimulationStateDto $state, float $tBeforeAc): SimulationStateDto
    {
        $deltaCool = max(0.0, $tBeforeAc - $state->temperature_c);
        if ($deltaCool <= 0.0) {
            return $state;
        }
        $h = $state->humidity_rh * (1.0 + 0.05 * $deltaCool);

        return $state->with([SimulationStateDto::SENSOR_HUMIDITY => $h]);
    }

    protected function loadState(ContainerSimulationSnapshot $snapshot): SimulationStateDto
    {
        if ($snapshot->exists && is_array($snapshot->sensor_state) && $snapshot->sensor_state !== []) {
            return SimulationStateDto::fromArray($snapshot->sensor_state);
        }

        return SimulationStateDto::fromArray(config('simulation.initial', []));
    }

    /**
     * Rental for worker/scheduled ticks: prefer last snapshot rental if still valid, else best current rental by dates.
     */
    protected function resolveRentalForBackgroundTick(Container $container, ContainerSimulationSnapshot $snapshot): ?Rental
    {
        $now = CarbonImmutable::now();

        if ($snapshot->rental_id !== null) {
            $fromSnapshot = Rental::query()->find($snapshot->rental_id);
            if ($fromSnapshot !== null && $this->rentalMatchesContainerAndTimeline($fromSnapshot, $container, $now)) {
                return $fromSnapshot;
            }
        }

        return $this->findBestCurrentRentalForContainer($container, $now);
    }

    /**
     * Active / in-progress / scheduled rental on this container whose [start_date, end_date] contains $now.
     */
    protected function findBestCurrentRentalForContainer(Container $container, CarbonImmutable $now): ?Rental
    {
        $nowStr = $now->format('Y-m-d H:i:s');

        return Rental::query()
            ->where('container_id', $container->id)
            ->whereIn('status', Rental::IOT_ELIGIBLE_STATUSES)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereNull('start_date')->orWhere('start_date', '<=', $now);
                })->orWhereIn('status', ['approved', 'scheduled']);
            })
            ->orderByRaw('CASE WHEN start_date IS NULL OR start_date <= ? THEN 0 ELSE 1 END', [$nowStr])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    protected function rentalMatchesContainerAndTimeline(Rental $rental, Container $container, CarbonImmutable $now): bool
    {
        if ((int) $rental->container_id !== (int) $container->id) {
            return false;
        }

        if (! in_array((string) $rental->status, Rental::IOT_ELIGIBLE_STATUSES, true)) {
            return false;
        }

        if ($rental->start_date !== null && $rental->start_date->gt($now)) {
            if (! in_array((string) $rental->status, ['approved', 'scheduled'], true)) {
                return false;
            }
        }

        if ($rental->end_date !== null && $rental->end_date->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, float>
     */
    protected function resolveTargets(?Rental $rental, ActuatorInputDto $actuators): array
    {
        $cfg = config('simulation.targets');
        $tMin = $rental && isset($rental->temperature_min) ? (float) $rental->temperature_min : 2.0;
        $tMax = $rental && isset($rental->temperature_max) ? (float) $rental->temperature_max : 8.0;
        if ($tMin >= $tMax) {
            $tMin = 2.0;
            $tMax = 8.0;
        }
        $mid = ($tMin + $tMax) / 2.0;
        $tempTarget = $actuators->acStatus ? $actuators->acTemp : $mid;

        return [
            SimulationStateDto::SENSOR_TEMPERATURE => $tempTarget,
            SimulationStateDto::SENSOR_HUMIDITY => (float) $cfg['default_humidity_rh'],
            SimulationStateDto::SENSOR_CO2 => (float) $cfg['default_co2_ppm'],
        ];
    }

    protected function applyConvergence(SimulationStateDto $state, array $targets): SimulationStateDto
    {
        $alpha = config('simulation.convergence');

        return $state->with([
            SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c
                + $alpha['alpha_temperature'] * ($targets[SimulationStateDto::SENSOR_TEMPERATURE] - $state->temperature_c),
            SimulationStateDto::SENSOR_HUMIDITY => $state->humidity_rh
                + $alpha['alpha_humidity'] * ($targets[SimulationStateDto::SENSOR_HUMIDITY] - $state->humidity_rh),
            SimulationStateDto::SENSOR_CO2 => $state->co2_ppm
                + $alpha['alpha_co2'] * ($targets[SimulationStateDto::SENSOR_CO2] - $state->co2_ppm),
        ]);
    }

    protected function applyAc(SimulationStateDto $state, ActuatorInputDto $actuators): SimulationStateDto
    {
        if (! $actuators->acStatus) {
            return $state;
        }
        $cfg = config('simulation.ac');
        $target = $actuators->acTemp;
        $t = $state->temperature_c;
        if ($t > $target) {
            $t -= $cfg['cooling_per_tick'];
            if ($t < $target) {
                $t = $target;
            }
        } elseif ($t < $target) {
            $t += $cfg['heating_per_tick'];
            if ($t > $target) {
                $t = $target;
            }
        }

        return $state->with([SimulationStateDto::SENSOR_TEMPERATURE => $t]);
    }

    protected function applyGaussianNoise(SimulationStateDto $state): SimulationStateDto
    {
        $sigma = config('simulation.noise');

        return $state->with([
            SimulationStateDto::SENSOR_TEMPERATURE => $state->temperature_c + $this->normalRandom(0, $sigma['sigma_temperature']),
            SimulationStateDto::SENSOR_HUMIDITY => $state->humidity_rh + $this->normalRandom(0, $sigma['sigma_humidity']),
            SimulationStateDto::SENSOR_CO2 => $state->co2_ppm + $this->normalRandom(0, $sigma['sigma_co2']),
            SimulationStateDto::SENSOR_NOISE => $state->noise_db + $this->normalRandom(0, $sigma['sigma_noise_db']),
            SimulationStateDto::SENSOR_PRESSURE => $state->pressure_hpa + $this->normalRandom(0, $sigma['sigma_pressure_hpa']),
        ]);
    }

    /**
     * Always written to metrics / Redis buffer even when {@see enabledTelemetryKeysForContainer} filters other keys.
     * These mirror actuators and auto-pump; skipping them leaves DB at 0 while snapshots/monitor still show real state.
     *
     * @return list<string>
     */
    protected function telemetryKeysAlwaysPersisted(): array
    {
        return [
            SimulationStateDto::SENSOR_DOOR_OPEN,
            SimulationStateDto::SENSOR_VENTILATION_ON,
            SimulationStateDto::SENSOR_PUMP_RUNNING,
            SimulationStateDto::SENSOR_WATER_LEVEL,
        ];
    }

    /**
     * Returns enabled telemetry keys for container, or null to allow all (backward compat when no container_sensors).
     *
     * @return array<string>|null
     */
    protected function enabledTelemetryKeysForContainer(Container $container): ?array
    {
        $container->loadMissing(['containerSensors.sensorType']);
        $sensors = $container->containerSensors->where('enabled', true);
        if ($sensors->isEmpty()) {
            return null;
        }
        $keys = $sensors->flatMap(fn ($cs) => $cs->sensorType?->telemetry_keys ?? [])->filter()->unique()->values()->all();

        return $keys === [] ? null : $keys;
    }

    protected function normalRandom(float $mean, float $stdDev): float
    {
        if ($stdDev <= 0) {
            return 0.0;
        }
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $z = sqrt(-2.0 * log(max($u1, 1e-12))) * cos(2.0 * M_PI * $u2);

        return $mean + $stdDev * $z;
    }

    protected function clampState(SimulationStateDto $state): SimulationStateDto
    {
        $c = config('simulation.clamps');

        return $state->with([
            SimulationStateDto::SENSOR_TEMPERATURE => max($c['temperature_min'], min($c['temperature_max'], $state->temperature_c)),
            SimulationStateDto::SENSOR_HUMIDITY => max($c['humidity_min'], min($c['humidity_max'], $state->humidity_rh)),
            SimulationStateDto::SENSOR_CO2 => max($c['co2_min'], min($c['co2_max'], $state->co2_ppm)),
            SimulationStateDto::SENSOR_NOISE => max($c['noise_db_min'], min($c['noise_db_max'], $state->noise_db)),
            SimulationStateDto::SENSOR_PRESSURE => max($c['pressure_hpa_min'], min($c['pressure_hpa_max'], $state->pressure_hpa)),
            SimulationStateDto::SENSOR_WATER_LEVEL => max($c['water_level_min'] ?? 0, min($c['water_level_max'] ?? 100, $state->water_level_pct)),
        ]);
    }

    protected function persist(
        Container $container,
        ?Rental $rental,
        SimulationStateDto $state,
        ActuatorInputDto $actuators,
        ContainerSimulationSnapshot $snapshot,
        bool $writeMetricsToDatabaseImmediately = false
    ): void {
        $now = CarbonImmutable::now();
        $rentalId = $rental?->id;

        $enabledKeys = $this->enabledTelemetryKeysForContainer($container);
        $alwaysPersisted = $this->telemetryKeysAlwaysPersisted();
        $definitions = config('iot_sensors.definitions', []);
        $allReadings = $state->toArray();
        $rows = [];
        foreach ($allReadings as $key => $value) {
            if ($enabledKeys !== null
                && ! in_array($key, $enabledKeys, true)
                && ! in_array($key, $alwaysPersisted, true)) {
                continue;
            }
            $unit = isset($definitions[$key]['unit']) ? (string) $definitions[$key]['unit'] : null;
            if ($unit === '') {
                $unit = null;
            }
            $rows[] = [
                'container_id' => $container->id,
                'rental_id' => $rentalId,
                'type' => $key,
                'value' => $value,
                'unit' => $unit,
                'meta' => null,
                'recorded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($container, $rentalId, $state, $actuators, $snapshot, $now) {
            $snapshot->container_id = $container->id;
            $snapshot->rental_id = $rentalId;
            $snapshot->sensor_state = $state->toArray();
            $snapshot->actuators = $actuators->toArray();
            $snapshot->last_tick_at = $now;
            $snapshot->save();
        });

        if ($rows !== [] && Schema::hasTable('metrics')) {
            DB::afterCommit(function () use ($rows, $writeMetricsToDatabaseImmediately) {
                $buffer = app(TelemetryWriteBuffer::class);
                if ($writeMetricsToDatabaseImmediately) {
                    $buffer->insertDirectly($rows);
                } else {
                    $buffer->pushMany($rows);
                }
            });
        }
    }
}
