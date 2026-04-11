<?php

namespace App\Console\Commands;

use App\Services\Metrics\TelemetryWriteBuffer;
use App\Services\SimulationService;
use Illuminate\Console\Command;
use Throwable;

class SimulationWorkerCommand extends Command
{
    protected bool $shouldStop = false;

    protected ?float $lastTelemetryFlushAt = null;

    protected $signature = 'simulation:worker
                            {--container= : Optional container ID (only this container is ticked)}
                            {--interval= : Seconds to sleep between full passes (overrides config)}
                            {--once : Run one pass over all matching containers then exit}';

    protected $description = 'Run continuous IoT simulation ticks in a background loop (use Supervisor/systemd in production)';

    public function handle(SimulationService $simulation): int
    {
        $this->registerSignalHandlers();

        $onlyId = $this->option('container') ? (int) $this->option('container') : null;
        $interval = $this->resolveIntervalSeconds();
        $once = (bool) $this->option('once');

        $this->components->info('IoT simulation worker started.');
        $this->line('Interval between passes: '.$interval.'s'.($once ? ' (single pass)' : '').'. Press Ctrl+C to stop.');

        $this->lastTelemetryFlushAt = microtime(true);

        do {
            $startedAt = microtime(true);

            try {
                $count = $simulation->tickAllIotActiveContainers($onlyId);
                $elapsed = round(microtime(true) - $startedAt, 3);
                $this->line(sprintf('[%s] Ticked %d container(s) in %ss.', now()->toIso8601String(), $count, $elapsed));
                $this->maybeFlushTelemetryBuffer();
            } catch (Throwable $e) {
                $this->components->error('Tick pass failed: '.$e->getMessage());
                report($e);
            }

            if ($once || $this->shouldStop) {
                break;
            }

            $this->sleepInterruptibly($interval);
        } while (! $this->shouldStop);

        $this->components->info('IoT simulation worker stopped.');

        return self::SUCCESS;
    }

    /**
     * Laravel's scheduler is not running inside the simulation container; without this,
     * Redis-buffered samples never reach `metrics`.
     */
    protected function maybeFlushTelemetryBuffer(): void
    {
        if (! config('metrics_buffer.enabled')) {
            return;
        }

        $interval = (int) config('metrics_buffer.worker_flush_interval_seconds', 60);
        $now = microtime(true);
        if ($this->lastTelemetryFlushAt !== null && ($now - $this->lastTelemetryFlushAt) < $interval) {
            return;
        }

        try {
            $inserted = app(TelemetryWriteBuffer::class)->flushFromRedis();
            $this->lastTelemetryFlushAt = $now;
            if ($inserted > 0) {
                $this->line(sprintf('[%s] Flushed %d aggregated metric row(s) to database.', now()->toIso8601String(), $inserted));
            }
        } catch (Throwable $e) {
            report($e);
            $this->components->warn('Telemetry buffer flush failed: '.$e->getMessage());
        }
    }

    protected function resolveIntervalSeconds(): float
    {
        if ($this->option('interval') !== null && $this->option('interval') !== '') {
            return max(0.1, (float) $this->option('interval'));
        }

        return (float) config('simulation.worker.sleep_seconds', 10);
    }

    /**
     * Light sleep in small steps so SIGTERM/SIGINT can stop the worker promptly.
     */
    protected function sleepInterruptibly(float $seconds): void
    {
        $until = microtime(true) + $seconds;
        while (microtime(true) < $until && ! $this->shouldStop) {
            usleep(100_000);
        }
    }

    protected function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function (): void {
            $this->shouldStop = true;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->shouldStop = true;
        });
    }
}
