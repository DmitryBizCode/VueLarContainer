<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RentalsBackfillApprovalRejectPaymentStatusCommand extends Command
{
    protected $signature = 'rentals:backfill-approval-reject-payment-status {--dry-run : Show rental IDs that would be updated}';

    protected $description = 'Backfill rentals.payment_status=rejected_by_approval for approval-rejected rentals marked by rejection_reason prefix.';

    private const PREFIX = 'APPROVAL_REJECTED:';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $base = DB::table('rentals')
            ->whereRaw('LOWER(status) = ?', ['rejected'])
            ->where(function ($q) {
                $q->where('rejection_reason', 'like', self::PREFIX.'%')
                    // Legacy records may not carry the prefix; treat any approval-queue rejection as rejected-by-approval.
                    ->orWhereNotNull('reviewed_at');
            })
            ->where(function ($q) {
                $q->whereNull('payment_status')
                    ->orWhereRaw('LOWER(payment_status) NOT IN (\'paid\', \'failed\', \'cancelled\', \'rejected_by_approval\')');
            });

        if ($dryRun) {
            $ids = $base->orderBy('id')->limit(500)->pluck('id')->all();
            $this->info('Dry run — rental IDs that would be updated (first 500): '.(count($ids) ? implode(', ', $ids) : '(none)'));

            return self::SUCCESS;
        }

        $updated = $base->update([
            'payment_status' => 'rejected_by_approval',
            'updated_at' => now(),
        ]);

        $this->info("Updated {$updated} rental(s).");

        return self::SUCCESS;
    }
}
