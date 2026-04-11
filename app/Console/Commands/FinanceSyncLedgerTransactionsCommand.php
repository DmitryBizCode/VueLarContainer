<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Services\RentalLedgerTransactionService;
use Illuminate\Console\Command;

class FinanceSyncLedgerTransactionsCommand extends Command
{
    protected $signature = 'finance:sync-ledger-transactions {--dry-run : List rental IDs that would get a ledger row without inserting}';

    protected $description = 'Create idempotent synthetic transactions for paid rentals that have no successful PSP transaction yet.';

    public function handle(RentalLedgerTransactionService $ledger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $would = [];

        Rental::query()
            ->where(function ($q) {
                $q->whereNotNull('payment_approved_at')
                    ->orWhereRaw('LOWER(payment_status) = ?', ['paid']);
            })
            ->orderBy('id')
            ->chunk(100, function ($rentals) use ($ledger, $dryRun, &$created, &$would) {
                foreach ($rentals as $rental) {
                    if (! $rental instanceof Rental) {
                        continue;
                    }
                    if ($dryRun) {
                        if ($ledger->wouldCreateLedgerTransaction($rental)) {
                            $would[] = $rental->id;
                        }

                        continue;
                    }

                    if ($ledger->syncLedgerTransactionForRental($rental)) {
                        $created++;
                    }
                }
            });

        if ($dryRun) {
            $this->info('Dry run — rental IDs that would receive a ledger transaction: '.(count($would) ? implode(', ', $would) : '(none)'));

            return self::SUCCESS;
        }

        $this->info("Created {$created} ledger transaction(s).");

        return self::SUCCESS;
    }
}
