<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telemetry write buffer
    |--------------------------------------------------------------------------
    |
    | When enabled, raw samples are pushed to Redis; schedule runs flushFromRedis()
    | every minute, which aggregates and INSERTs one row per sensor group (median for
    | continuous values; logical-OR / max for discrete door/pump/vent-style sensors).
    | When disabled (e.g. PHPUnit), writers use direct INSERT of raw rows.
    |
    */

    'enabled' => (bool) env('METRICS_BUFFER_ENABLED', true),

    'redis_connection' => env('METRICS_BUFFER_REDIS_CONNECTION', 'default'),

    'list_key' => env('METRICS_BUFFER_LIST_KEY', 'metrics:telemetry_buffer'),

    /** Max raw JSON rows drained from Redis per flush (safety cap). */
    'flush_max_raw_rows' => (int) env('METRICS_BUFFER_FLUSH_MAX_RAW_ROWS', 250_000),

    'max_list_length' => (int) env('METRICS_BUFFER_MAX_LIST_LEN', 50000),

    /** How many tail list elements to scan when merging pending samples into monitor API (LRANGE). */
    'peek_max_tail' => max(100, (int) env('METRICS_BUFFER_PEEK_MAX_TAIL', 8000)),

    /*
    | Default: atomically RENAME the buffer list to a staging key, then LRANGE it. Only one
    | flusher gets each backlog; concurrent LPOP interleaving (schedule + worker) cannot
    | split one minute into two half-batches. Set false only if your Redis proxy forbids RENAME.
    */
    'flush_atomic_rename' => (bool) env('METRICS_BUFFER_FLUSH_ATOMIC_RENAME', true),

    /*
    | When flush_atomic_rename is false: optional SET NX lock before sequential LPOP (same Redis).
    */
    'flush_lock_key' => env('METRICS_BUFFER_FLUSH_LOCK_KEY', 'metrics:telemetry_buffer_flush'),
    'flush_lock_ttl_seconds' => max(30, (int) env('METRICS_BUFFER_FLUSH_LOCK_TTL_SECONDS', 120)),
    'flush_lock_wait_seconds' => max(1, (int) env('METRICS_BUFFER_FLUSH_LOCK_WAIT_SECONDS', 10)),

    'flush_use_lock' => (bool) env('METRICS_BUFFER_FLUSH_USE_LOCK', false),

    /*
    | `simulation:worker` calls flush on this interval so Docker stacks without
    | schedule:work still persist Redis medians to the database.
    */
    'worker_flush_interval_seconds' => max(10, (int) env('METRICS_BUFFER_WORKER_FLUSH_INTERVAL_SECONDS', 60)),

    'queue_connection' => env('METRICS_BUFFER_QUEUE_CONNECTION'),

    'queue' => env('METRICS_BUFFER_QUEUE', 'telemetry'),

    /*
    | Discrete 0/1 sensors (door, pump, ventilation). On buffer flush: if any sample in the
    | window was "on" (> 0), the DB row is 1; median is only used for continuous sensors.
    | Types are matched case-insensitively; optional regex extends beyond the explicit list.
    */
    'binary_sensor_types' => [
        'door_open',
        'ventilation_on',
        'pump_running',
    ],

    /** If non-empty, types whose lowercase name matches this PCRE are treated as discrete (max/OR). */
    'binary_sensor_type_pattern' => env('METRICS_BUFFER_BINARY_TYPE_PATTERN', '/_(on|open|running)$/u'),

];
