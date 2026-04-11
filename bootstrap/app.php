<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Sub-minute: run `php artisan schedule:work` or cron hitting `schedule:run` frequently enough.
        if (config('simulation.schedule_tick_enabled')) {
            $schedule->command('simulation:tick')->everyTenSeconds();
        }
        // Median aggregates: drain Redis and INSERT once per minute (no raw rows in DB when buffer on).
        $schedule->call(function (): void {
            if (! config('metrics_buffer.enabled')) {
                return;
            }
            app(\App\Services\Metrics\TelemetryWriteBuffer::class)->flushFromRedis();
        })->everyMinute();
        $schedule->command('metrics:prune-ended-rentals')->hourly();
        $schedule->command('metrics:prune-null-metrics')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->statefulApi();

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LogRequestContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return \Inertia\Inertia::render('Errors/404', [
                'status' => 404,
            ])->toResponse($request)->setStatusCode(404);
        });
    })->create();
