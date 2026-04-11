<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Max points drawn per sensor chart (monitor page)
    |--------------------------------------------------------------------------
    |
    | Raw rows in `metrics` can be thousands; only the latest N samples in the
    | selected time range are sent to the chart for performance and readability.
    |
    */
    'chart_max_points' => (int) env('IOT_MONITOR_CHART_MAX_POINTS', 30),

    /*
    |--------------------------------------------------------------------------
    | Max raw samples loaded per sensor for monitor bucketing / full-window stats
    |--------------------------------------------------------------------------
    |
    | Time buckets aggregate all samples in the window up to this cap. If the
    | query returns this many rows, stats may omit older rows (stats_truncated).
    |
    */
    'chart_window_samples_max' => (int) env('IOT_MONITOR_CHART_WINDOW_SAMPLES_MAX', 10000),

    /*
    |--------------------------------------------------------------------------
    | Continuous sensor bucket value: median or mean
    |--------------------------------------------------------------------------
    |
    | Binary/discrete types (door_open, pump_running, …) always use max/OR per bucket.
    |
    */
    'chart_bucket_continuous' => strtolower(trim((string) env('IOT_MONITOR_CHART_BUCKET_CONTINUOUS', 'median'))),

    /*
    |--------------------------------------------------------------------------
    | Extended lookback when the selected window has no samples
    |--------------------------------------------------------------------------
    |
    | If no metrics fall inside [from, to], charts load the latest N points with
    | recorded_at <= to as far back as this many days (same container / rental scope).
    |
    */
    'extended_lookback_days' => (int) env('IOT_MONITOR_EXTENDED_LOOKBACK_DAYS', 7),

    /*
    | Live "latest" readings for polling: prefer worker snapshot when this fresh,
    | while historical chart points still come from `metrics` (minute median flush).
    */
    'snapshot_latest_max_age_seconds' => max(15, (int) env('IOT_MONITOR_SNAPSHOT_LATEST_MAX_AGE_SECONDS', 120)),

];
