<?php

namespace App\DataTransferObjects;

readonly class SimulationStateDto
{
    public const SENSOR_TEMPERATURE = 'temperature_c';

    public const SENSOR_HUMIDITY = 'humidity_rh';

    public const SENSOR_CO2 = 'co2_ppm';

    public const SENSOR_NOISE = 'noise_db';

    public const SENSOR_PRESSURE = 'pressure_hpa';

    public const SENSOR_DOOR_OPEN = 'door_open';

    public const SENSOR_PUMP_RUNNING = 'pump_running';

    public const SENSOR_WATER_LEVEL = 'water_level_pct';

    public const SENSOR_VENTILATION_ON = 'ventilation_on';

    public function __construct(
        public float $temperature_c,
        public float $humidity_rh,
        public float $co2_ppm,
        public float $noise_db = 42.0,
        public float $pressure_hpa = 1013.25,
        public float $door_open = 0.0,
        public float $pump_running = 0.0,
        public float $water_level_pct = 0.0,
        public float $ventilation_on = 0.0,
    ) {}

    public static function fromArray(array $data): self
    {
        $initial = config('simulation.initial', []);

        return new self(
            temperature_c: (float) ($data[self::SENSOR_TEMPERATURE] ?? $initial['temperature_c'] ?? 6.0),
            humidity_rh: (float) ($data[self::SENSOR_HUMIDITY] ?? $initial['humidity_rh'] ?? 62.0),
            co2_ppm: (float) ($data[self::SENSOR_CO2] ?? $initial['co2_ppm'] ?? 720.0),
            noise_db: (float) ($data[self::SENSOR_NOISE] ?? $initial['noise_db'] ?? 42.0),
            pressure_hpa: (float) ($data[self::SENSOR_PRESSURE] ?? $initial['pressure_hpa'] ?? 1013.25),
            door_open: (float) ($data[self::SENSOR_DOOR_OPEN] ?? $initial['door_open'] ?? 0.0),
            pump_running: (float) ($data[self::SENSOR_PUMP_RUNNING] ?? $initial['pump_running'] ?? 0.0),
            water_level_pct: (float) ($data[self::SENSOR_WATER_LEVEL] ?? $initial['water_level_pct'] ?? 0.0),
            ventilation_on: (float) ($data[self::SENSOR_VENTILATION_ON] ?? $initial['ventilation_on'] ?? 0.0),
        );
    }

    public function toArray(): array
    {
        return [
            self::SENSOR_TEMPERATURE => $this->temperature_c,
            self::SENSOR_HUMIDITY => $this->humidity_rh,
            self::SENSOR_CO2 => $this->co2_ppm,
            self::SENSOR_NOISE => $this->noise_db,
            self::SENSOR_PRESSURE => $this->pressure_hpa,
            self::SENSOR_DOOR_OPEN => $this->door_open,
            self::SENSOR_PUMP_RUNNING => $this->pump_running,
            self::SENSOR_WATER_LEVEL => $this->water_level_pct,
            self::SENSOR_VENTILATION_ON => $this->ventilation_on,
        ];
    }

    public function with(array $overrides): self
    {
        $a = $this->toArray();

        return self::fromArray(array_merge($a, $overrides));
    }
}
