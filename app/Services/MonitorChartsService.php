<?php

namespace App\Services;

use App\DataTransferObjects\SimulationStateDto;
use App\Models\Metric;
use App\Models\Rental;
use App\Support\IotMonitorSeriesBuilder;
use App\Support\MetricsSampleAggregator;
use Carbon\CarbonImmutable;

class MonitorChartsService
{
    public function __construct(
        private readonly TelemetryAnalyticsService $telemetryAnalytics
    ) {}

    /**
     * Build iot_charts payload for Monitor page (sensors + door_events).
     */
    public function build(Rental $rental, CarbonImmutable $from, CarbonImmutable $to, string $seriesMode = 'window'): array
    {
        $container = $rental->container;
        $periodHours = (int) ceil($from->diffInHours($to));
        $start = $from;

        $temperatureMin = isset($rental->temperature_min) ? (float) $rental->temperature_min : 2.0;
        $temperatureMax = isset($rental->temperature_max) ? (float) $rental->temperature_max : 8.0;
        if ($temperatureMin >= $temperatureMax) {
            $temperatureMin = 2.0;
            $temperatureMax = 8.0;
        }
        $stepHours = 2;

        $doorEventsSeries = $this->buildDoorEvents($container, $rental, $from, $to, $periodHours, $start);

        $sensorDefinitions = config('iot_sensors.definitions', []);
        $sensorOrder = $this->resolveSensorOrder($container);
        $iotSensorPanels = $this->buildSensorPanels(
            $container,
            $rental,
            $from,
            $to,
            $start,
            $periodHours,
            $stepHours,
            $temperatureMin,
            $temperatureMax,
            $sensorOrder,
            $sensorDefinitions,
            $seriesMode
        );

        $payload = [
            'period_hours' => $periodHours,
            'step_hours' => $stepHours,
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'series_mode' => $seriesMode === 'raw_tail' ? 'raw_tail' : 'window',
            'sensors' => $iotSensorPanels,
            'door_events' => $doorEventsSeries,
        ];

        if ($container !== null) {
            $payload['iot_latest'] = $this->telemetryAnalytics->latestForContainer(
                (int) $container->id,
                (int) $rental->id,
                (int) $rental->user_id
            );
        }

        return $payload;
    }

    /**
     * Shrink payload embedded in Inertia's HTML data-page attribute. Full time series arrive via
     * the monitor-charts API immediately on mount (Monitor.vue fetchCharts). Avoids very large
     * initial JSON that some browsers (notably Chrome) handle poorly when hydrating.
     *
     * @param  array<string, mixed>  $payload  Output of {@see build()}
     * @return array<string, mixed>
     */
    public function trimPayloadForInertiaInitialLoad(array $payload): array
    {
        unset($payload['iot_latest']);

        if (isset($payload['sensors']) && is_array($payload['sensors'])) {
            $emptyStats = [
                'min' => null,
                'max' => null,
                'mean' => null,
                'last' => null,
                'variance' => null,
                'count' => 0,
            ];
            $payload['sensors'] = array_values(array_map(static function (array $sensor) use ($emptyStats): array {
                $sensor['series'] = [];
                $sensor['stats'] = $emptyStats;
                $sensor['stats_truncated'] = false;
                $sensor['discrete'] = (bool) ($sensor['discrete'] ?? false);

                return $sensor;
            }, $payload['sensors']));
        }

        $payload['door_events'] = [];

        return $payload;
    }

    /**
     * @return array<int, array{timestamp: string, status: string}>
     */
    private function buildDoorEvents($container, Rental $rental, CarbonImmutable $from, CarbonImmutable $to, int $periodHours, CarbonImmutable $start): array
    {
        $doorEventsSeries = [];

        if ($container && $container->iot_active) {
            $cid = (int) $container->id;
            $rid = (int) $rental->id;
            $uid = (int) $rental->user_id;
            $rentalIds = Rental::query()
                ->where('container_id', $cid)
                ->where('user_id', $uid)
                ->pluck('id')
                ->all();
            $doorReadings = Metric::query()
                ->forContainer($cid)
                ->where(function ($q) use ($rentalIds, $rid) {
                    if ($rentalIds === []) {
                        $q->where('rental_id', $rid)->orWhereNull('rental_id');
                    } else {
                        $q->whereNull('rental_id')->orWhereIn('rental_id', $rentalIds);
                    }
                })
                ->forType(SimulationStateDto::SENSOR_DOOR_OPEN)
                ->whereBetween('recorded_at', [
                    $from->copy()->subMinutes(2),
                    $to->copy()->addMinutes(2),
                ])
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'value'])
                ->filter(fn (Metric $row) => $row->recorded_at >= $from && $row->recorded_at <= $to)
                ->values();

            $prev = null;
            foreach ($doorReadings as $row) {
                $open = (float) $row->value >= 0.5;
                $v = $open ? 1 : 0;
                if ($prev === null || $prev !== $v) {
                    $doorEventsSeries[] = [
                        'timestamp' => $row->recorded_at->toIso8601String(),
                        'status' => $open ? 'open' : 'closed',
                    ];
                }
                $prev = $v;
            }
        }

        if ($doorEventsSeries === [] && $periodHours > 0 && $container && ! $container->iot_active) {
            $doorSteps = max(1, (int) floor($periodHours / 6));
            for ($i = 0; $i <= $periodHours; $i += $doorSteps) {
                $pointTime = $start->addHours($i);
                if ($pointTime->gt($to)) {
                    break;
                }
                $doorEventsSeries[] = [
                    'timestamp' => $pointTime->toIso8601String(),
                    'status' => $i === 0 || $pointTime->gte($to) ? 'closed' : 'open',
                ];
            }
        }

        return $doorEventsSeries;
    }

    /**
     * @return array<string>
     */
    private function resolveSensorOrder($container): array
    {
        $definitions = config('iot_sensors.definitions', []);
        $fallbackOrder = config('iot_sensors.order', []);

        if (! $container) {
            return $this->filterSensorKeysToDefinitions($fallbackOrder, $definitions);
        }

        $enabled = $container->containerSensors()
            ->where('enabled', true)
            ->with('sensorType')
            ->get()
            ->flatMap(fn ($cs) => $cs->sensorType?->telemetry_keys ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fromContainer = $this->filterSensorKeysToDefinitions($enabled, $definitions);

        if ($fromContainer !== []) {
            return $fromContainer;
        }

        return $this->filterSensorKeysToDefinitions($fallbackOrder, $definitions);
    }

    /**
     * @param  array<string>  $keys
     * @param  array<string, mixed>  $definitions
     * @return array<string>
     */
    private function filterSensorKeysToDefinitions(array $keys, array $definitions): array
    {
        $out = [];
        foreach ($keys as $key) {
            $k = (string) $key;
            if ($k !== '' && isset($definitions[$k]) && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /**
     * @param  array<string>  $sensorOrder
     * @param  array<string, mixed>  $sensorDefinitions
     * @return array<int, array<string, mixed>>
     */
    private function buildSensorPanels(
        $container,
        Rental $rental,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $start,
        int $periodHours,
        int $stepHours,
        float $temperatureMin,
        float $temperatureMax,
        array $sensorOrder,
        array $sensorDefinitions,
        string $seriesMode = 'window'
    ): array {
        $iotSensorPanels = [];
        $seriesMode = $seriesMode === 'raw_tail' ? 'raw_tail' : 'window';

        foreach ($sensorOrder as $sensorKey) {
            $def = $sensorDefinitions[$sensorKey] ?? null;
            if ($def === null) {
                continue;
            }

            $series = [];
            $source = 'demo';
            $samplesInRange = null;
            $bufferSamplesInRange = 0;
            $chartMaxPoints = $this->telemetryAnalytics->monitorChartMaxPoints();
            $usedExtendedLookback = false;
            $stats = null;
            $statsTruncated = null;
            $discrete = null;

            if ($container && $container->iot_active) {
                $chart = $this->telemetryAnalytics->chartSeriesForMonitor(
                    (int) $container->id,
                    (string) $sensorKey,
                    $from,
                    $to,
                    (int) $rental->id,
                    (int) $rental->user_id,
                    $seriesMode
                );
                $series = $chart['series'];
                $source = $chart['telemetry_backed'] ? 'telemetry' : 'defaults';
                $samplesInRange = (int) ($chart['samples_in_range'] ?? 0);
                $bufferSamplesInRange = (int) ($chart['buffer_samples_in_range'] ?? 0);
                $chartMaxPoints = (int) ($chart['chart_max_points'] ?? $chartMaxPoints);
                $usedExtendedLookback = (bool) ($chart['used_extended_lookback'] ?? false);
                $stats = $chart['stats'] ?? $this->telemetryAnalytics->seriesStats($series);
                $statsTruncated = (bool) ($chart['stats_truncated'] ?? false);
                $discrete = (bool) ($chart['discrete'] ?? MetricsSampleAggregator::usesDiscreteOrAggregation((string) $sensorKey));
            } elseif ($container) {
                $series = IotMonitorSeriesBuilder::syntheticSeries(
                    (string) $sensorKey,
                    $start,
                    $periodHours,
                    $stepHours,
                    $temperatureMin,
                    $temperatureMax,
                    $to
                );
                $stats = $this->telemetryAnalytics->seriesStats($series);
                $statsTruncated = false;
                $discrete = MetricsSampleAggregator::usesDiscreteOrAggregation((string) $sensorKey);
            }

            $decimals = (int) ($def['decimals'] ?? 1);
            $series = IotMonitorSeriesBuilder::roundSeriesForDisplay($series, $decimals);
            if ($stats === null) {
                $stats = $this->telemetryAnalytics->seriesStats($series);
            }
            if ($statsTruncated === null) {
                $statsTruncated = false;
            }
            if ($discrete === null) {
                $discrete = MetricsSampleAggregator::usesDiscreteOrAggregation((string) $sensorKey);
            }

            $iotSensorPanels[] = [
                'key' => $sensorKey,
                'label' => $def['label'],
                'description' => $def['description'] ?? '',
                'unit' => $def['unit'],
                'decimals' => $decimals,
                'stroke' => $def['stroke'] ?? '#2563eb',
                'fill_from' => $def['fill_from'] ?? '#0f766e',
                'fill_to' => $def['fill_to'] ?? '#0ea5e9',
                'series' => $series,
                'stats' => $stats,
                'stats_truncated' => $statsTruncated,
                'discrete' => $discrete,
                'source' => $source,
                'samples_in_range' => $samplesInRange,
                'buffer_samples_in_range' => $bufferSamplesInRange,
                'chart_max_points' => $chartMaxPoints,
                'used_extended_lookback' => $usedExtendedLookback,
            ];
        }

        return $iotSensorPanels;
    }
}
