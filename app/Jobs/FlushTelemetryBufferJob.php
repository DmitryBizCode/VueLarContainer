<?php

namespace App\Jobs;

use App\Services\Metrics\TelemetryWriteBuffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlushTelemetryBufferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $c = config('metrics_buffer.queue_connection');
        if (is_string($c) && $c !== '') {
            $this->onConnection($c);
        }
        $queue = config('metrics_buffer.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(TelemetryWriteBuffer $buffer): void
    {
        $buffer->flushFromRedis();
    }
}
