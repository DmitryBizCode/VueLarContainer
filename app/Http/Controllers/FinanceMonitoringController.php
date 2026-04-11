<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
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
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('paid','completed','succeeded','success') THEN transactions.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('pending','processing') THEN transactions.amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) = 'failed' THEN transactions.amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('paid','completed','succeeded','success') THEN 1 END) as paid_count,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('pending','processing') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(transactions.status) = 'failed' THEN 1 END) as failed_count,
                COUNT(*) as total_transactions,
                MAX(transactions.transaction_date) as last_transaction_at
            ")
            ->first();

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
                'rentals.status as rental_status',
                'rentals.payment_status as rental_payment_status',
            ])
            ->orderByDesc('transactions.transaction_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('FinanceMonitoring', [
            'synthetic_ledger_prefix' => (string) config('finance.ledger.external_id_prefix', 'ledger:rental:'),
            'filters' => $filters,
            'overview' => [
                'paidAmount' => (float) ($overview->paid_amount ?? 0),
                'pendingAmount' => (float) ($overview->pending_amount ?? 0),
                'failedAmount' => (float) ($overview->failed_amount ?? 0),
                'paidCount' => (int) ($overview->paid_count ?? 0),
                'pendingCount' => (int) ($overview->pending_count ?? 0),
                'failedCount' => (int) ($overview->failed_count ?? 0),
                'totalTransactions' => (int) ($overview->total_transactions ?? 0),
                'lastTransactionAt' => $overview->last_transaction_at ?? null,
            ],
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
