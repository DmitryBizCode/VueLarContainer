<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule-driven tick (simulation:tick)
    |--------------------------------------------------------------------------
    |
    | When false, only `simulation:worker` should tick containers (avoids double
    | ticks if both scheduler and worker run). Sub-minute ticks need schedule:work.
    |
    */
    'schedule_tick_enabled' => filter_var(
        env('SIMULATION_SCHEDULE_TICK_ENABLED', true),
        FILTER_VALIDATE_BOOL
    ),

    'tick_seconds' => (int) env('SIMULATION_TICK_SECONDS', 10),

    'ambient' => [
        'temperature_c' => (float) env('SIMULATION_AMBIENT_TEMP_C', 22.0),
        'humidity_rh' => (float) env('SIMULATION_AMBIENT_HUMIDITY_RH', 55.0),
        'co2_ppm' => (float) env('SIMULATION_AMBIENT_CO2_PPM', 420.0),
    ],

    'targets' => [
        'default_temperature_c' => (float) env('SIMULATION_DEFAULT_TARGET_TEMP_C', 5.0),
        'default_humidity_rh' => (float) env('SIMULATION_DEFAULT_TARGET_HUMIDITY_RH', 65.0),
        'default_co2_ppm' => (float) env('SIMULATION_DEFAULT_TARGET_CO2_PPM', 800.0),
    ],

    'convergence' => [
        'alpha_temperature' => (float) env('SIMULATION_ALPHA_TEMP', 0.08),
        'alpha_humidity' => (float) env('SIMULATION_ALPHA_HUMIDITY', 0.06),
        'alpha_co2' => (float) env('SIMULATION_ALPHA_CO2', 0.04),
    ],

    'ventilation' => [
        'co2_drop_per_tick' => (float) env('SIMULATION_VENT_CO2_DROP', 45.0),
        'pull_efficiency' => (float) env('SIMULATION_VENT_PULL_EFF', 0.12),
    ],

    'humidifier' => [
        'delta_humidity_rh' => 2.5,
        'delta_temperature_c' => -0.2,
    ],

    'ac' => [
        'cooling_per_tick' => (float) env('SIMULATION_AC_COOL_PER_TICK', 0.35),
        'heating_per_tick' => (float) env('SIMULATION_AC_HEAT_PER_TICK', 0.25),
    ],

    'heater' => [
        'delta_temperature_c' => 0.15,
    ],

    'lights' => [
        'delta_temperature_c' => 0.02,
        'delta_humidity_rh' => -0.03,
    ],

    'pump' => [
        'delta_humidity_rh' => -0.4,
        'water_level_drop_per_tick' => (float) env('SIMULATION_PUMP_WATER_DROP', 3.0),
    ],

    'water_level' => [
        'recovery_per_tick' => (float) env('SIMULATION_WATER_RECOVERY', 0.8),
        'condensation_humidity_factor' => (float) env('SIMULATION_WATER_CONDENSATION_HUMIDITY', 0.02),
    ],

    /*
    | Spray / humidifier → condensation → water sump (when RH above threshold).
    */
    'condensation' => [
        'rh_threshold' => (float) env('SIMULATION_CONDENSATION_RH', 68.0),
        'water_gain_per_tick' => (float) env('SIMULATION_CONDENSATION_WATER_GAIN', 0.55),
    ],

    'freshener' => [
        'delta_humidity_rh' => (float) env('SIMULATION_FRESHENER_DELTA_RH', 1.15),
    ],

    /*
    | Auto drain when sump level reaches threshold (effective pump even if user pump off).
    */
    'auto_pump' => [
        'threshold_pct' => (float) env('SIMULATION_AUTO_PUMP_THRESHOLD', 72.0),
    ],

    'door' => [
        'exchange_efficiency' => (float) env('SIMULATION_DOOR_EXCHANGE_EFF', 0.25),
    ],

    'cargo_respiration' => [
        'delta_temperature_c' => 0.04,
        'delta_humidity_rh' => 0.06,
        'delta_co2_ppm' => 3.5,
    ],

    'noise' => [
        'sigma_temperature' => (float) env('SIMULATION_SIGMA_TEMP', 0.08),
        'sigma_humidity' => (float) env('SIMULATION_SIGMA_HUMIDITY', 0.35),
        'sigma_co2' => (float) env('SIMULATION_SIGMA_CO2', 2.0),
        'sigma_noise_db' => (float) env('SIMULATION_SIGMA_NOISE_DB', 0.5),
        'sigma_pressure_hpa' => (float) env('SIMULATION_SIGMA_PRESSURE', 0.15),
    ],

    'clamps' => [
        'temperature_min' => -30.0,
        'temperature_max' => 45.0,
        'humidity_min' => 5.0,
        'humidity_max' => 98.0,
        'co2_min' => 350.0,
        'co2_max' => 5000.0,
        'noise_db_min' => 28.0,
        'noise_db_max' => 95.0,
        'pressure_hpa_min' => 980.0,
        'pressure_hpa_max' => 1040.0,
        'water_level_min' => 0.0,
        'water_level_max' => 100.0,
    ],

    'initial' => [
        'temperature_c' => 6.0,
        'humidity_rh' => 62.0,
        'co2_ppm' => 720.0,
        'noise_db' => 42.0,
        'pressure_hpa' => 1013.25,
        'door_open' => 0.0,
        'pump_running' => 0.0,
        'water_level_pct' => 0.0,
        'ventilation_on' => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuous worker (simulation:worker)
    |--------------------------------------------------------------------------
    |
    | Long-running process that ticks all IoT-active containers in a loop.
    | Run under Supervisor/systemd; keep interval reasonable to limit DB load.
    |
    */
    'worker' => [
        'sleep_seconds' => max(0.1, (float) env('SIMULATION_WORKER_SLEEP_SECONDS', 10)),
    ],
];
