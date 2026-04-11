<?php

use App\Http\Controllers\Api\IotAuditController;
use App\Http\Controllers\Api\MonitorChartLayoutController;
use App\Http\Controllers\Api\RentalTelemetryToggleController;
use App\Http\Controllers\Api\SimulationActuatorController;
use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/rentals/{rental}/monitor-charts', [\App\Http\Controllers\Api\MonitorChartsController::class, 'index'])
        ->name('api.rentals.monitor-charts');
    Route::post('/rentals/{rental}/toggle-telemetry', [RentalTelemetryToggleController::class, 'toggle'])
        ->name('api.rentals.toggle-telemetry');
    Route::get('/rentals/{rental}/telemetry', [TelemetryController::class, 'telemetry'])
        ->name('api.rentals.telemetry');
    Route::get('/rentals/{rental}/analytics', [TelemetryController::class, 'analytics'])
        ->name('api.rentals.analytics');
    Route::patch('/rentals/{rental}/simulation/actuators', [SimulationActuatorController::class, 'update'])
        ->name('api.rentals.simulation.actuators');
    Route::get('/rentals/{rental}/iot-audit', [IotAuditController::class, 'index'])
        ->name('api.rentals.iot-audit');
    Route::get('/rentals/{rental}/telemetry/export-csv', [MonitorChartLayoutController::class, 'exportCsv'])
        ->name('api.rentals.telemetry.export-csv');

    Route::get('/chart-layouts', [MonitorChartLayoutController::class, 'index'])
        ->name('api.chart-layouts.index');
    Route::post('/chart-layouts', [MonitorChartLayoutController::class, 'store'])
        ->name('api.chart-layouts.store');
    Route::get('/chart-layouts/{layout}', [MonitorChartLayoutController::class, 'show'])
        ->name('api.chart-layouts.show');
    Route::put('/chart-layouts/{layout}', [MonitorChartLayoutController::class, 'update'])
        ->name('api.chart-layouts.update');
    Route::delete('/chart-layouts/{layout}', [MonitorChartLayoutController::class, 'destroy'])
        ->name('api.chart-layouts.destroy');

    Route::get('/rentals/{rental}/chart-layouts', [MonitorChartLayoutController::class, 'index'])
        ->name('api.rentals.chart-layouts.index');
    Route::post('/rentals/{rental}/chart-layouts', [MonitorChartLayoutController::class, 'store'])
        ->name('api.rentals.chart-layouts.store');
});
