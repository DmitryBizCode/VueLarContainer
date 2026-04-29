<?php

namespace App\Http\Controllers;

use App\Support\FinanceStatusGroups;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'q' => isset($validated['q']) ? trim($validated['q']) : null,
        ];

        $userId = (int) $request->user()->id;

        $baseQuery = DB::table('transactions')
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->where('rentals.user_id', $userId);

        $filteredQuery = $this->applyFilters(clone $baseQuery, $filters);

        $overview = (clone $filteredQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."') THEN transactions.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['pending'])."') THEN transactions.amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['failure'])."') THEN transactions.amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."') THEN 1 END) as paid_count,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['pending'])."') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['failure'])."') THEN 1 END) as failed_count,
                COUNT(*) as total_transactions,
                MAX(transactions.transaction_date) as last_transaction_at
            ")
            ->first();

        $transactionsByStatus = (clone $filteredQuery)
            ->selectRaw('LOWER(COALESCE(transactions.status, \'unknown\')) as status, COUNT(*) as count, COALESCE(SUM(transactions.amount), 0) as amount_sum')
            ->groupBy(DB::raw("LOWER(COALESCE(transactions.status, 'unknown'))"))
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->status => [
                    'count' => (int) $r->count,
                    'amount_sum' => (float) $r->amount_sum,
                ],
            ])
            ->all();

        $rentalsByStatus = DB::table('rentals')
            ->where('rentals.user_id', $userId)
            ->selectRaw('LOWER(COALESCE(status, \'unknown\')) as status, COUNT(*) as count, COALESCE(SUM(price), 0) as price_sum')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->status => [
                    'count' => (int) $r->count,
                    'price_sum' => (float) $r->price_sum,
                ],
            ])
            ->all();

        $rentalsByPaymentStatus = DB::table('rentals')
            ->where('rentals.user_id', $userId)
            ->selectRaw('LOWER(COALESCE(payment_status, \'unknown\')) as payment_status, COUNT(*) as count, COALESCE(SUM(price), 0) as price_sum')
            ->groupBy('payment_status')
            ->orderBy('payment_status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->payment_status => [
                    'count' => (int) $r->count,
                    'price_sum' => (float) $r->price_sum,
                ],
            ])
            ->all();

        $rejectedApproval = [
            'count' => (int) ($rentalsByPaymentStatus['rejected_by_approval']['count'] ?? 0),
            'lostRevenuePriceSum' => (float) ($rentalsByPaymentStatus['rejected_by_approval']['price_sum'] ?? 0),
            'txAmountSum' => (float) DB::table('transactions')
                ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
                ->where('rentals.user_id', $userId)
                ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
                ->sum('transactions.amount'),
        ];

        $transactions = (clone $filteredQuery)
            ->select([
                'transactions.id',
                'transactions.rental_id',
                'transactions.amount',
                'transactions.currency',
                'transactions.status',
                'transactions.payment_method',
                'transactions.external_provider_id',
                'transactions.transaction_date',
                'transactions.status_note',
                'transactions.refund_reason',
                'rentals.status as rental_status',
                'rentals.payment_status as rental_payment_status',
                'rentals.rejection_reason as rental_rejection_reason',
            ])
            ->orderByDesc('transactions.transaction_date')
            ->paginate(15)
            ->withQueryString();

        $syntheticLedgerPrefix = (string) config('finance.ledger.external_id_prefix', 'ledger:rental:');
        $syntheticRejectedTransactions = DB::table('rentals')
            ->where('rentals.user_id', $userId)
            ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->orderByDesc('rentals.updated_at')
            ->limit(50)
            ->get([
                'rentals.id',
                'rentals.price',
                'rentals.created_at',
                'rentals.updated_at',
                'rentals.rejection_reason',
            ])
            ->map(fn ($r) => [
                'id' => $syntheticLedgerPrefix.(int) $r->id.':rejected_by_approval',
                'rental_id' => (int) $r->id,
                'amount' => (float) ($r->price ?? 0),
                'currency' => 'USD',
                'status' => 'failed',
                'payment_method' => 'approval_reject',
                'external_provider_id' => $syntheticLedgerPrefix.(int) $r->id,
                'transaction_date' => Carbon::parse($r->updated_at ?? $r->created_at)->toISOString(),
                'status_note' => 'Rejected by approval',
                'refund_reason' => null,
                'rental_status' => 'rejected',
                'rental_payment_status' => 'rejected_by_approval',
                'rental_rejection_reason' => (string) ($r->rejection_reason ?? ''),
                'synthetic' => true,
            ])
            ->values()
            ->all();

        $syntheticPendingApprovalTransactions = DB::table('rentals')
            ->where('rentals.user_id', $userId)
            ->whereRaw('LOWER(rentals.status) = ?', ['pending_approval'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->orderByDesc('rentals.updated_at')
            ->limit(50)
            ->get([
                'rentals.id',
                'rentals.price',
                'rentals.payment_status',
                'rentals.created_at',
                'rentals.updated_at',
            ])
            ->map(fn ($r) => [
                'id' => $syntheticLedgerPrefix.(int) $r->id.':pending_approval',
                'rental_id' => (int) $r->id,
                'amount' => (float) ($r->price ?? 0),
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'approval_pending',
                'external_provider_id' => $syntheticLedgerPrefix.(int) $r->id,
                'transaction_date' => Carbon::parse($r->updated_at ?? $r->created_at)->toISOString(),
                'status_note' => 'Awaiting approval',
                'refund_reason' => null,
                'rental_status' => 'pending_approval',
                'rental_payment_status' => (string) ($r->payment_status ?? 'pending'),
                'rental_rejection_reason' => '',
                'synthetic' => true,
            ])
            ->values()
            ->all();

        $syntheticTransactions = array_values(array_merge($syntheticPendingApprovalTransactions, $syntheticRejectedTransactions));
        usort($syntheticTransactions, static function (array $a, array $b): int {
            return strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
        });

        $syntheticFailedCount = count($syntheticRejectedTransactions);
        $syntheticFailedAmount = array_sum(array_map(fn ($t) => (float) ($t['amount'] ?? 0), $syntheticRejectedTransactions));
        $syntheticPendingCount = count($syntheticPendingApprovalTransactions);
        $syntheticPendingAmount = array_sum(array_map(fn ($t) => (float) ($t['amount'] ?? 0), $syntheticPendingApprovalTransactions));

        return Inertia::render('FinanceMonitoring', [
            'synthetic_ledger_prefix' => $syntheticLedgerPrefix,
            'filters' => $filters,
            'overview' => [
                'paidAmount' => (float) ($overview->paid_amount ?? 0),
                'pendingAmount' => (float) ($overview->pending_amount ?? 0) + $syntheticPendingAmount,
                'failedAmount' => (float) ($overview->failed_amount ?? 0) + $syntheticFailedAmount,
                'paidCount' => (int) ($overview->paid_count ?? 0),
                'pendingCount' => (int) ($overview->pending_count ?? 0) + $syntheticPendingCount,
                'failedCount' => (int) ($overview->failed_count ?? 0) + $syntheticFailedCount,
                'totalTransactions' => (int) ($overview->total_transactions ?? 0) + $syntheticFailedCount + $syntheticPendingCount,
                'lastTransactionAt' => $overview->last_transaction_at ?? null,
            ],
            'transactionsByStatus' => $transactionsByStatus,
            'rentalsByStatus' => $rentalsByStatus,
            'rentalsByPaymentStatus' => $rentalsByPaymentStatus,
            'rejectedApproval' => $rejectedApproval,
            'syntheticTransactions' => $syntheticTransactions,
            'transactions' => $transactions,
        ]);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (filled($filters['status'] ?? null)) {
            $query->whereRaw('LOWER(transactions.status) = ?', [strtolower((string) $filters['status'])]);
        }

        if (filled($filters['payment_method'] ?? null)) {
            $query->whereRaw('LOWER(transactions.payment_method) = ?', [strtolower((string) $filters['payment_method'])]);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('transactions.transaction_date', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('transactions.transaction_date', '<=', $filters['date_to']);
        }

        if (filled($filters['q'] ?? null)) {
            $search = (string) $filters['q'];

            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('transactions.external_provider_id', 'like', '%'.$search.'%')
                    ->orWhere('transactions.rental_id', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }
}
