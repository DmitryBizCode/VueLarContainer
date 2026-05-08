<?php

/**
 * IoT sensors exposed on rental monitoring (aligned with SimulationStateDto / telemetry rows).
 * Order defines default display order on Monitor.
 */
return [

    'order' => [
        'temperature_c',
        'humidity_rh',
        'co2_ppm',
        'noise_db',
        'pressure_hpa',
    ],

    'definitions' => [

        'temperature_c' => [
            'label' => 'Temperature',
            'description' => 'Internal cargo hold temperature',
            'unit' => '°C',
            'decimals' => 1,
            'stroke' => '#2563eb',
            'fill_from' => '#0f766e',
            'fill_to' => '#0ea5e9',
        ],

        'humidity_rh' => [
            'label' => 'Humidity',
            'description' => 'Relative humidity (RH)',
            'unit' => '%',
            'decimals' => 1,
            'stroke' => '#0891b2',
            'fill_from' => '#06b6d4',
            'fill_to' => '#22d3ee',
        ],

        'co2_ppm' => [
            'label' => 'CO₂',
            'description' => 'Carbon dioxide concentration',
            'unit' => 'ppm',
            'decimals' => 0,
            'stroke' => '#d97706',
            'fill_from' => '#f59e0b',
            'fill_to' => '#fcd34d',
        ],

        'noise_db' => [
            'label' => 'Noise',
            'description' => 'Noise level inside the container',
            'unit' => 'dB',
            'decimals' => 1,
            'stroke' => '#7c3aed',
            'fill_from' => '#a78bfa',
            'fill_to' => '#c4b5fd',
        ],

        'pressure_hpa' => [
            'label' => 'Pressure',
            'description' => 'Atmospheric pressure (equivalent)',
            'unit' => 'hPa',
            'decimals' => 1,
            'stroke' => '#475569',
            'fill_from' => '#64748b',
            'fill_to' => '#94a3b8',
        ],

        'door_open' => [
            'label' => 'Doors',
            'description' => 'Door open/closed state',
            'unit' => '',
            'decimals' => 0,
            'stroke' => '#059669',
            'fill_from' => '#10b981',
            'fill_to' => '#34d399',
        ],

        'pump_running' => [
            'label' => 'Pump',
            'description' => 'Drainage pump',
            'unit' => '',
            'decimals' => 0,
            'stroke' => '#0d9488',
            'fill_from' => '#14b8a6',
            'fill_to' => '#2dd4bf',
        ],

        'water_level_pct' => [
            'label' => 'Water level',
            'description' => 'Fill percentage',
            'unit' => '%',
            'decimals' => 1,
            'stroke' => '#0284c7',
            'fill_from' => '#0ea5e9',
            'fill_to' => '#38bdf8',
        ],

        'ventilation_on' => [
            'label' => 'Ventilation',
            'description' => 'Ventilation on/off',
            'unit' => '',
            'decimals' => 0,
            'stroke' => '#6366f1',
            'fill_from' => '#818cf8',
            'fill_to' => '#a5b4fc',
        ],
    ],

];
