<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable subquery: rental_ids that have at least one successful transaction (per FinanceStatusGroups).
 */
final class FinancePaidRentalSubquery
{
    public static function distinctRentalIds(): Builder
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        return Transaction::query()
            ->select('rental_id')
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->distinct();
    }
}
