<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsPruneNullMetricsCommand extends Command
{
    protected $signature = 'metrics:prune-null-metrics
                            {--days=7 : Delete rows with rental_id NULL older than this many days}
                            {--chunk=2000 : Rows per DELETE batch}';

    protected $description = 'Delete aged metrics in the NULL-rental partition (metrics_p_null / default bucket)';

    public function handle(): int
    {
        if (! Schema::hasTable('metrics')) {
            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $chunk = max(100, (int) $this->option('chunk'));
        $before = Carbon::now()->subDays($days);

        $total = 0;
        do {
            $deleted = DB::table('metrics')
                ->whereNull('rental_id')
                ->where('recorded_at', '<', $before)
                ->limit($chunk)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Deleted {$total} NULL-rental metric row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
