<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Transaction;
use DateTimeInterface;

class RentalLedgerTransactionService
{
    /** Matches FinanceMonitoringController / AdminFinanceController success buckets. */
    private const SUCCESS_STATUSES = ['paid', 'completed', 'succeeded', 'success'];

    /**
     * Whether {@see syncLedgerTransactionForRental} would insert a row (dry-run / diagnostics).
     */
    public function wouldCreateLedgerTransaction(Rental $rental): bool
    {
        $externalId = $this->syntheticExternalId($rental);
        if ($externalId === '') {
            return false;
        }

        if (Transaction::query()->where('rental_id', $rental->id)->where('external_provider_id', $externalId)->exists()) {
            return false;
        }

        if ($this->rentalHasSuccessfulTransaction($rental->id)) {
            return false;
        }

        return $this->isEligibleForLedgerEntry($rental);
    }

    /**
     * Create a single synthetic ledger transaction when the rental is treated as
     * paid for accounting and there is no successful PSP transaction yet.
     *
     * @return bool True if a new row was inserted.
     */
    public function syncLedgerTransactionForRental(Rental $rental): bool
    {
        if (! $this->wouldCreateLedgerTransaction($rental)) {
            return false;
        }

        $externalId = $this->syntheticExternalId($rental);

        Transaction::query()->create([
            'rental_id' => $rental->id,
            'amount' => $rental->price,
            'currency' => (string) config('finance.ledger.default_currency', 'USD'),
            'status' => (string) config('finance.ledger.default_status', 'completed'),
            'external_provider_id' => $externalId,
            'refund_reason' => null,
            'transaction_date' => $this->resolveTransactionDate($rental),
            'payment_method' => (string) config('finance.ledger.default_payment_method', 'bank_transfer'),
        ]);

        return true;
    }

    public function syntheticExternalId(Rental $rental): string
    {
        $prefix = (string) config('finance.ledger.external_id_prefix', 'ledger:rental:');

        return $prefix.$rental->id;
    }

    public function isSyntheticExternalId(?string $externalProviderId): bool
    {
        if ($externalProviderId === null || $externalProviderId === '') {
            return false;
        }

        $prefix = (string) config('finance.ledger.external_id_prefix', 'ledger:rental:');

        return str_starts_with($externalProviderId, $prefix);
    }

    private function isEligibleForLedgerEntry(Rental $rental): bool
    {
        return strtolower((string) $rental->payment_status) === 'paid';
    }

    private function rentalHasSuccessfulTransaction(int $rentalId): bool
    {
        return Transaction::query()
            ->where('rental_id', $rentalId)
            ->whereRaw('LOWER(status) IN (?,?,?,?)', self::SUCCESS_STATUSES)
            ->exists();
    }

    private function resolveTransactionDate(Rental $rental): DateTimeInterface
    {
        return $rental->payment_approved_at ?? $rental->updated_at ?? now();
    }
}
