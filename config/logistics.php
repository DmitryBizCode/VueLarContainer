<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Port operations (loading / discharge)
    |--------------------------------------------------------------------------
    */
    'port_operations_min_days' => (int) env('LOGISTICS_PORT_OPS_MIN_DAYS', 2),
    'port_operations_max_days' => (int) env('LOGISTICS_PORT_OPS_MAX_DAYS', 4),

    /*
    |--------------------------------------------------------------------------
    | Rental end after final port arrival (business rule)
    |--------------------------------------------------------------------------
    */
    'post_arrival_min_days' => (int) env('LOGISTICS_POST_ARRIVAL_MIN_DAYS', 1),
    'post_arrival_max_days' => (int) env('LOGISTICS_POST_ARRIVAL_MAX_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | Vessel availability at origin
    |--------------------------------------------------------------------------
    */
    'require_vessel_at_origin' => filter_var(env('LOGISTICS_REQUIRE_VESSEL_AT_ORIGIN', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Vessel statuses treated as operational for scheduling
    |--------------------------------------------------------------------------
    */
    'vessel_operational_statuses' => ['active', 'in_transit', 'in_port', 'scheduled'],

    /*
    |--------------------------------------------------------------------------
    | Predictive vessel scheduling: look-ahead horizon
    |--------------------------------------------------------------------------
    */
    'vessel_forecast_days' => (int) env('LOGISTICS_VESSEL_FORECAST_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Rental request: route dropdown (Existing route)
    |--------------------------------------------------------------------------
    |
    | When false, only "open" routes with at least one available container
    | physically at the route's origin port are listed (aligns with dispatch from port).
    |
    */
    'rental_request_show_all_open_routes' => filter_var(
        env('LOGISTICS_RENTAL_REQUEST_SHOW_ALL_OPEN_ROUTES', false),
        FILTER_VALIDATE_BOOL
    ),

];
