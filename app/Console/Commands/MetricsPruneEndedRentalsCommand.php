<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Services\Metrics\MetricsPartitionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetricsPruneEndedRentalsCommand extends Command
{
    protected $signature = 'metrics:prune-ended-rentals
                            {--grace-hours=0 : Extra hours after rental end before dropping partition (0 = drop when end moment has passed)}
                            {--dry-run : List partitions that would be dropped without dropping}';

    protected $description = 'Drop PostgreSQL metrics partitions for ended rentals after COALESCE(actual_return_date, end_date). Extending those dates on the rental row delays the drop.';

    public function handle(MetricsPartitionManager $partitions): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->info('Skipped: not using PostgreSQL.');

            return self::SUCCESS;
        }

        $graceHours = max(0, (int) $this->option('grace-hours'));
        $cutoff = Carbon::now()->subHours($graceHours);
        $dryRun = (bool) $this->option('dry-run');

        $ids = Rental::query()
            ->whereIn('status', ['completed', 'cancelled'])
            ->where(function ($q) {
                $q->whereNotNull('actual_return_date')
                    ->orWhereNotNull('end_date');
            })
            ->whereRaw('COALESCE(actual_return_date, end_date) <= ?', [$cutoff])
            ->orderBy('id')
            ->pluck('id');

        $dropped = 0;
        foreach ($ids as $rentalId) {
            if (! $partitions->partitionExistsForRentalId((int) $rentalId)) {
                continue;
            }
            if ($dryRun) {
                $this->line("Would drop partition for rental_id={$rentalId}");
                $dropped++;

                continue;
            }
            if ($partitions->dropPartitionForRentalId((int) $rentalId)) {
                $this->info("Dropped metrics partition for rental_id={$rentalId}");
                $dropped++;
            }
        }

        $this->info($dryRun ? "Dry run: {$dropped} partition(s)." : "Done. Dropped {$dropped} partition(s).");

        return self::SUCCESS;
    }
}
