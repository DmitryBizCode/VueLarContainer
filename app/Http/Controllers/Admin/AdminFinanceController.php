<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Container;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Notifications\NotificationService;
use App\Services\RentalLedgerTransactionService;
use App\Support\FinanceStatusGroups;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinanceController extends Controller
{
    private const REJECT_REASON_NON_PAYMENT = 'Non-payment for service';

    public function index(Request $request): Response
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'staff_sort_by' => ['nullable', 'string', 'in:name,approved_count,approved_sum,commission,bonus'],
            'staff_sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'transaction_sort_by' => ['nullable', 'string', 'in:id,rental_id,amount,status,transaction_date'],
            'transaction_sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'report_date_from' => ['nullable', 'date'],
            'report_date_to' => ['nullable', 'date', 'after_or_equal:report_date_from'],
        ]);

        $baseQuery = Transaction::query()->join('rentals', 'rentals.id', '=', 'transactions.rental_id');

        $overview = (clone $baseQuery)->selectRaw("
            COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."') THEN transactions.amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['pending'])."') THEN transactions.amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['failure'])."') THEN transactions.amount ELSE 0 END), 0) as failed_amount,
            COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."') THEN 1 END) as paid_count,
            COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['pending'])."') THEN 1 END) as pending_count,
            COUNT(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['failure'])."') THEN 1 END) as failed_count,
            COUNT(*) as total_transactions
        ")->first();

        $transactionsByStatus = Cache::remember('admin_finance:transactions_by_status:v1', now()->addMinutes(5), function () {
            return Transaction::query()
                ->selectRaw('LOWER(COALESCE(status, \'unknown\')) as status, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount_sum')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->mapWithKeys(fn ($r) => [
                    (string) $r->status => [
                        'count' => (int) $r->count,
                        'amount_sum' => (float) $r->amount_sum,
                    ],
                ])
                ->all();
        });

        $rentalsByStatus = Cache::remember('admin_finance:rentals_by_status:v1', now()->addMinutes(5), function () {
            return Rental::query()
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
        });

        $rentalsByPaymentStatus = Cache::remember('admin_finance:rentals_by_payment_status:v1', now()->addMinutes(5), function () {
            return Rental::query()
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
        });

        $rejectedApproval = Cache::remember('admin_finance:rejected_approval:v1', now()->addMinutes(5), function () {
            $row = Rental::query()
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(price), 0) as price_sum')
                ->whereRaw("LOWER(COALESCE(payment_status, '')) = 'rejected_by_approval'")
                ->first();

            $txAmount = (float) Transaction::query()
                ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
                ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
                ->sum('transactions.amount');

            return [
                'count' => (int) ($row?->count ?? 0),
                'lostRevenuePriceSum' => (float) ($row?->price_sum ?? 0),
                'txAmountSum' => $txAmount,
            ];
        });

        $rentalsSummary = Rental::query()
            ->leftJoinSub(
                Transaction::query()
                    ->select('rental_id')
                    ->whereRaw("LOWER(status) IN ('paid','completed','succeeded','success')")
                    ->distinct(),
                'paid_tx',
                'paid_tx.rental_id',
                '=',
                'rentals.id'
            )
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(rentals.payment_status) = 'paid' OR paid_tx.rental_id IS NOT NULL THEN rentals.price ELSE 0 END), 0) as revenue_paid,
                COALESCE(SUM(CASE WHEN LOWER(rentals.payment_status) NOT IN ('paid') AND paid_tx.rental_id IS NULL THEN rentals.price ELSE 0 END), 0) as revenue_pending,
                COUNT(CASE WHEN LOWER(rentals.payment_status) = 'paid' OR paid_tx.rental_id IS NOT NULL THEN 1 END) as rentals_paid_count,
                COUNT(CASE WHEN LOWER(rentals.payment_status) NOT IN ('paid') AND paid_tx.rental_id IS NULL THEN 1 END) as rentals_pending_count,
                COUNT(*) as rentals_total
            ")
            ->first();

        $containersSummary = [
            'total' => Container::query()->count(),
            'revenue_from_rentals' => (float) $this->rentalsPaidRevenueQuery()->sum('price'),
        ];

        $pendingOrders = Rental::query()
            ->with(['user:id,first_name,last_name,email', 'container:id,serial_number', 'originPort:id,name', 'destinationPort:id,name'])
            ->whereRaw("LOWER(payment_status) IN ('pending', 'unpaid')")
            ->whereIn('status', ['approved', 'pending_approval'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Rental $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'payment_status' => $r->payment_status,
                'price' => (float) $r->price,
                'created_at' => $r->created_at?->toISOString(),
                'customer' => trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? '')) ?: $r->user?->email ?? '—',
                'container' => $r->container?->serial_number,
                'origin' => $r->originPort?->name,
                'destination' => $r->destinationPort?->name,
            ]);

        $pendingPaymentApprovals = [];
        if (Schema::hasColumn('rentals', 'payment_approved_at')) {
            $pendingPaymentApprovals = Rental::query()
                ->with(['user:id,first_name,last_name,email', 'container:id,serial_number', 'originPort:id,name', 'destinationPort:id,name'])
                ->where('status', 'approved')
                ->whereRaw("LOWER(payment_status) IN ('pending', 'unpaid')")
                ->whereNull('payment_approved_at')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn (Rental $r) => [
                    'id' => $r->id,
                    'price' => (float) $r->price,
                    'created_at' => $r->created_at?->toISOString(),
                    'customer' => trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? '')) ?: $r->user?->email ?? '—',
                    'origin' => $r->originPort?->name,
                    'destination' => $r->destinationPort?->name,
                ]);
        }

        $transactionsWithPending = Transaction::query()
            ->with(['rental.user:id,first_name,last_name,email', 'rental.container:id,serial_number'])
            ->whereRaw("LOWER(status) IN ('pending', 'processing')")
            ->orderByDesc('transaction_date')
            ->limit(30)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'rental_id' => $t->rental_id,
                'amount' => (float) $t->amount,
                'currency' => $t->currency,
                'status' => $t->status,
                'rental' => $t->rental ? [
                    'customer' => trim(($t->rental->user?->first_name ?? '').' '.($t->rental->user?->last_name ?? '')) ?: $t->rental->user?->email ?? '—',
                    'container' => $t->rental->container?->serial_number,
                ] : null,
            ]);

        $query = Transaction::query()->with('rental.user');
        $txSortBy = $validated['transaction_sort_by'] ?? 'transaction_date';
        $txSortOrder = $validated['transaction_sort_order'] ?? 'desc';
        if (in_array($txSortBy, ['id', 'rental_id', 'amount', 'status', 'transaction_date'], true)) {
            $query->orderBy($txSortBy, $txSortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('transaction_date');
        }

        if (! empty($validated['status'])) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($validated['status'])]);
        }
        if (! empty($validated['payment_method'])) {
            $query->whereRaw('LOWER(payment_method) = ?', [strtolower($validated['payment_method'])]);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('transaction_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('transaction_date', '<=', $validated['date_to']);
        }
        if (! empty($validated['q'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('external_provider_id', 'like', '%'.$validated['q'].'%')
                    ->orWhere('rental_id', 'like', '%'.$validated['q'].'%');
            });
        }

        $transactions = $query->paginate(15)->withQueryString();

        $transactions->getCollection()->transform(function (Transaction $t) {
            return [
                'id' => $t->id,
                'rental_id' => $t->rental_id,
                'amount' => (float) $t->amount,
                'currency' => $t->currency,
                'status' => $t->status,
                'payment_method' => $t->payment_method,
                'external_provider_id' => $t->external_provider_id,
                'transaction_date' => $t->transaction_date?->toISOString(),
                'status_note' => $t->status_note,
                'refund_reason' => $t->refund_reason,
                'rental_status' => $t->rental?->status,
                'rental_payment_status' => $t->rental?->payment_status,
            ];
        });

        $syntheticLedgerPrefix = (string) config('finance.ledger.external_id_prefix', 'ledger:rental:');
        $syntheticRejectedTransactions = Rental::query()
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
                'synthetic' => true,
            ])
            ->values()
            ->all();

        $syntheticPendingApprovalTransactions = Rental::query()
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
                'synthetic' => true,
            ])
            ->values()
            ->all();

        $syntheticTransactions = array_values(array_merge($syntheticPendingApprovalTransactions, $syntheticRejectedTransactions));
        usort($syntheticTransactions, static function (array $a, array $b): int {
            return strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
        });

        $chartData = Cache::remember('admin_finance:chart_data:v1', now()->addMinutes(5), fn () => $this->buildChartData());
        $chartPaymentMethods = Cache::remember('admin_finance:chart_payment_methods:v1', now()->addMinutes(10), fn () => $this->buildChartPaymentMethods());
        $chartByRoute = Cache::remember('admin_finance:chart_by_route:v1', now()->addMinutes(10), fn () => $this->buildChartByRoute());
        $chartTopContainers = Cache::remember('admin_finance:chart_top_containers:v1', now()->addMinutes(10), fn () => $this->buildChartTopContainers());
        $failedTrend = Cache::remember('admin_finance:failed_trend:v1', now()->addMinutes(5), fn () => $this->buildFailedTrend());
        $yoyMom = Cache::remember('admin_finance:yoy_mom:v1', now()->addMinutes(10), fn () => $this->buildYoyMom());
        $dailyBreakdown = Cache::remember('admin_finance:daily_breakdown:v1', now()->addMinutes(5), fn () => $this->buildDailyBreakdown());
        $metrics = Cache::remember('admin_finance:metrics:v1', now()->addMinutes(10), fn () => $this->buildMetrics());

        $reportData = null;
        if (! empty($validated['report_date_from']) && ! empty($validated['report_date_to'])) {
            $reportData = $this->buildReportData($validated['report_date_from'], $validated['report_date_to']);
        }

        $statusOptions = Cache::remember('admin_finance:status_options:v1', now()->addMinutes(30), function () {
            $distinct = Transaction::query()
                ->selectRaw('LOWER(COALESCE(status, \'unknown\')) as status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->values()
                ->all();

            return $distinct;
        });

        $paymentStatusOptions = Cache::remember('admin_finance:payment_status_options:v1', now()->addMinutes(30), function () {
            return Rental::query()
                ->selectRaw('LOWER(COALESCE(payment_status, \'unknown\')) as payment_status')
                ->distinct()
                ->orderBy('payment_status')
                ->pluck('payment_status')
                ->filter()
                ->values()
                ->all();
        });

        $syntheticFailedCount = count($syntheticRejectedTransactions);
        $syntheticFailedAmount = array_sum(array_map(fn ($t) => (float) ($t['amount'] ?? 0), $syntheticRejectedTransactions));
        $syntheticPendingCount = count($syntheticPendingApprovalTransactions);
        $syntheticPendingAmount = array_sum(array_map(fn ($t) => (float) ($t['amount'] ?? 0), $syntheticPendingApprovalTransactions));

        return Inertia::render('Admin/Finance/Index', [
            'synthetic_ledger_prefix' => $syntheticLedgerPrefix,
            'syntheticTransactions' => $syntheticTransactions,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'q' => $validated['q'] ?? null,
                'staff_sort_by' => $validated['staff_sort_by'] ?? null,
                'staff_sort_order' => $validated['staff_sort_order'] ?? null,
                'transaction_sort_by' => $validated['transaction_sort_by'] ?? null,
                'transaction_sort_order' => $validated['transaction_sort_order'] ?? null,
            ],
            'overview' => [
                'paidAmount' => (float) ($overview->paid_amount ?? 0),
                'pendingAmount' => (float) ($overview->pending_amount ?? 0) + $syntheticPendingAmount,
                'failedAmount' => (float) ($overview->failed_amount ?? 0) + $syntheticFailedAmount,
                'paidCount' => (int) ($overview->paid_count ?? 0),
                'pendingCount' => (int) ($overview->pending_count ?? 0) + $syntheticPendingCount,
                'failedCount' => (int) ($overview->failed_count ?? 0) + $syntheticFailedCount,
                'totalTransactions' => (int) ($overview->total_transactions ?? 0) + $syntheticFailedCount + $syntheticPendingCount,
            ],
            'transactionsByStatus' => $transactionsByStatus,
            'rentalsByStatus' => $rentalsByStatus,
            'rentalsByPaymentStatus' => $rentalsByPaymentStatus,
            'rejectedApproval' => $rejectedApproval,
            'rentalsSummary' => [
                'revenuePaid' => (float) ($rentalsSummary->revenue_paid ?? 0),
                'revenuePending' => (float) ($rentalsSummary->revenue_pending ?? 0),
                'rentalsPaidCount' => (int) ($rentalsSummary->rentals_paid_count ?? 0),
                'rentalsPendingCount' => (int) ($rentalsSummary->rentals_pending_count ?? 0),
                'rentalsTotal' => (int) ($rentalsSummary->rentals_total ?? 0),
            ],
            'containersSummary' => $containersSummary,
            'chartData' => $chartData,
            'chartPaymentMethods' => $chartPaymentMethods,
            'chartByRoute' => $chartByRoute,
            'chartTopContainers' => $chartTopContainers,
            'failedTrend' => $failedTrend,
            'yoyMom' => $yoyMom,
            'dailyBreakdown' => $dailyBreakdown,
            'metrics' => $metrics,
            'pendingOrders' => $pendingOrders,
            'pendingPaymentApprovals' => $pendingPaymentApprovals,
            'pendingTransactions' => $transactionsWithPending,
            'transactions' => $transactions,
            'statusOptions' => $statusOptions,
            'paymentStatusOptions' => $paymentStatusOptions,
            'reportData' => $reportData,
            'reportDateFrom' => $validated['report_date_from'] ?? null,
            'reportDateTo' => $validated['report_date_to'] ?? null,
            'staffStats' => $this->buildStaffStats(
                $validated['date_from'] ?? null,
                $validated['date_to'] ?? null,
                $validated['staff_sort_by'] ?? 'approved_sum',
                $validated['staff_sort_order'] ?? 'desc'
            ),
        ]);
    }

    private function buildStaffStats(?string $dateFrom, ?string $dateTo, string $sortBy, string $sortOrder): array
    {
        $query = Rental::query()
            ->whereNotNull('reviewed_by')
            ->where('status', 'approved')
            ->selectRaw('reviewed_by as user_id, COUNT(*) as approved_count, COALESCE(SUM(price), 0) as approved_sum');

        if ($dateFrom) {
            $query->whereDate('rentals.reviewed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('rentals.reviewed_at', '<=', $dateTo);
        }
        $query->groupBy('reviewed_by');

        $rows = $query->get();
        $userIds = $rows->pluck('user_id')->unique()->filter()->all();
        $users = $userIds ? \App\Models\User::query()->whereIn('id', $userIds)->get()->keyBy('id') : collect();

        $result = $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);
            $sum = (float) $row->approved_sum;
            $rate = $user ? (float) ($user->commission_rate ?? 0) : 0;
            $commission = $sum * $rate / 100;
            $bonusType = $user?->bonus_type ?? null;
            $bonusVal = $user ? (float) ($user->bonus_value ?? 0) : 0;
            $bonus = 0.0;
            if ($bonusType === 'fixed') {
                $bonus = $bonusVal;
            } elseif ($bonusType === 'percent') {
                $bonus = $sum * $bonusVal / 100;
            }

            return [
                'user_id' => $row->user_id,
                'name' => $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email : 'User #'.$row->user_id,
                'email' => $user?->email ?? null,
                'approved_count' => (int) $row->approved_count,
                'approved_sum' => round($sum, 2),
                'commission_rate' => $rate,
                'commission' => round($commission, 2),
                'bonus_type' => $bonusType,
                'bonus_value' => $bonusVal,
                'bonus' => round($bonus, 2),
            ];
        });

        $allowedSort = ['name', 'approved_count', 'approved_sum', 'commission', 'bonus'];
        if (in_array($sortBy, $allowedSort, true)) {
            $result = $sortOrder === 'asc'
                ? $result->sortBy($sortBy === 'name' ? 'name' : $sortBy)
                : $result->sortByDesc($sortBy === 'name' ? 'name' : $sortBy);
        }

        return $result->values()->all();
    }

    private function buildReportData(string $dateFrom, string $dateTo): array
    {
        $overview = Transaction::query()
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(status) IN ('paid','completed','succeeded','success') THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(status) IN ('pending','processing') THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(status) IN ('failed','cancelled') THEN amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(status) IN ('paid','completed','succeeded','success') THEN 1 END) as paid_count,
                COUNT(CASE WHEN LOWER(status) IN ('pending','processing') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(status) IN ('failed','cancelled') THEN 1 END) as failed_count,
                COUNT(*) as total_transactions
            ")->first();

        $syntheticLedgerPrefix = (string) config('finance.ledger.external_id_prefix', 'ledger:rental:');

        // Rentals awaiting approval may have no PSP transaction yet, but should still be visible in finance reports.
        $syntheticPendingApproval = Rental::query()
            ->whereRaw('LOWER(status) = ?', ['pending_approval'])
            ->whereDate('updated_at', '>=', $dateFrom)
            ->whereDate('updated_at', '<=', $dateTo)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->get(['price']);
        $syntheticPendingCount = $syntheticPendingApproval->count();
        $syntheticPendingAmount = (float) $syntheticPendingApproval->sum('price');

        $syntheticRejectedApproval = Rental::query()
            ->whereRaw('LOWER(payment_status) = ?', ['rejected_by_approval'])
            ->whereDate('updated_at', '>=', $dateFrom)
            ->whereDate('updated_at', '<=', $dateTo)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->get(['price']);
        $syntheticFailedCount = $syntheticRejectedApproval->count();
        $syntheticFailedAmount = (float) $syntheticRejectedApproval->sum('price');

        return [
            'overview' => [
                'paidAmount' => (float) ($overview->paid_amount ?? 0),
                'pendingAmount' => (float) ($overview->pending_amount ?? 0) + $syntheticPendingAmount,
                'failedAmount' => (float) ($overview->failed_amount ?? 0) + $syntheticFailedAmount,
                'paidCount' => (int) ($overview->paid_count ?? 0),
                'pendingCount' => (int) ($overview->pending_count ?? 0) + $syntheticPendingCount,
                'failedCount' => (int) ($overview->failed_count ?? 0) + $syntheticFailedCount,
                'totalTransactions' => (int) ($overview->total_transactions ?? 0) + $syntheticPendingCount + $syntheticFailedCount,
            ],
            'staffStats' => $this->buildStaffStats($dateFrom, $dateTo, 'approved_sum', 'desc'),
        ];
    }

    /** Rentals that are "paid": payment_status = paid OR have at least one paid transaction. */
    private function rentalsPaidRevenueQuery()
    {
        $paidRentalIds = Transaction::query()
            ->whereRaw("LOWER(status) IN ('paid','completed','succeeded','success')")
            ->distinct()
            ->pluck('rental_id');

        return Rental::query()->where(function ($q) use ($paidRentalIds) {
            $q->whereRaw('LOWER(payment_status) = ?', ['paid'])
                ->orWhereIn('id', $paidRentalIds);
        });
    }

    private function buildChartData(): array
    {
        $months = [];
        $now = Carbon::now()->startOfMonth();
        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i);
            $end = $start->copy()->endOfMonth();
            $months[] = [
                'label' => $start->format('M Y'),
                'key' => $start->format('Y-m'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        $txGroups = FinanceStatusGroups::transactionGroups();

        $ymExpr = $this->sqlYearMonthExpr('transaction_date');

        $txRows = Transaction::query()
            ->selectRaw($ymExpr.' as ym')
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) IN ('".implode("','", $txGroups['success'])."') THEN amount ELSE 0 END), 0) as paid")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) IN ('".implode("','", $txGroups['pending'])."') THEN amount ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) IN ('".implode("','", $txGroups['failure'])."') THEN amount ELSE 0 END), 0) as failed")
            ->whereDate('transaction_date', '>=', $months[0]['start'])
            ->whereDate('transaction_date', '<=', $months[count($months) - 1]['end'])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $rentalCounts = Rental::query()
            ->selectRaw($this->sqlYearMonthExpr('created_at').' as ym')
            ->selectRaw('COUNT(*) as rentalCount')
            ->whereDate('created_at', '>=', $months[0]['start'])
            ->whereDate('created_at', '<=', $months[count($months) - 1]['end'])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $paidRentalRevenue = Rental::query()
            ->selectRaw($this->sqlYearMonthExpr('transactions.transaction_date').' as ym')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as rentalRevenue')
            ->join('transactions', 'transactions.rental_id', '=', 'rentals.id')
            ->whereRaw("LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."')")
            ->whereDate('transactions.transaction_date', '>=', $months[0]['start'])
            ->whereDate('transactions.transaction_date', '<=', $months[count($months) - 1]['end'])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        return array_map(function (array $m) use ($txRows, $rentalCounts, $paidRentalRevenue) {
            $ym = $m['key'];
            $tx = $txRows->get($ym);
            $rc = $rentalCounts->get($ym);
            $rr = $paidRentalRevenue->get($ym);

            return [
                'label' => $m['label'],
                'paid' => (float) ($tx->paid ?? 0),
                'pending' => (float) ($tx->pending ?? 0),
                'failed' => (float) ($tx->failed ?? 0),
                'rentalRevenue' => (float) ($rr->rentalRevenue ?? 0),
                'rentalCount' => (int) ($rc->rentalCount ?? 0),
            ];
        }, $months);
    }

    private function sqlYearMonthExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        if ($driver === 'pgsql') {
            return "to_char({$column}, 'YYYY-MM')";
        }

        // mysql/mariadb default
        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function buildChartPaymentMethods(): array
    {
        $rows = Transaction::query()
            ->selectRaw('LOWER(COALESCE(payment_method, \'unknown\')) as method, SUM(amount) as total')
            ->whereRaw("LOWER(status) IN ('paid','completed','succeeded','success')")
            ->groupBy(DB::raw('LOWER(COALESCE(payment_method, \'unknown\'))'))
            ->get();

        return $rows->map(fn ($r) => ['label' => ucfirst($r->method), 'value' => (float) $r->total])->all();
    }

    private function buildChartByRoute(): array
    {
        $rows = Rental::query()
            ->select('rentals.route_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereIn('rentals.id', function ($q) {
                $q->select('rental_id')
                    ->from('transactions')
                    ->whereRaw("LOWER(status) IN ('paid','completed','succeeded','success')");
            })
            ->groupBy('rentals.route_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->with('route.originPort:id,name', 'route.destinationPort:id,name')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $route = $r->route;
            $label = $route
                ? ($route->originPort?->name ?? '?').' → '.($route->destinationPort?->name ?? '?')
                : 'Route #'.($r->route_id ?? '—');
            $result[] = ['label' => $label, 'value' => (float) $r->revenue];
        }

        return $result;
    }

    private function buildChartTopContainers(): array
    {
        return Rental::query()
            ->select('rentals.container_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereIn('rentals.id', function ($q) {
                $q->select('rental_id')
                    ->from('transactions')
                    ->whereRaw("LOWER(status) IN ('paid','completed','succeeded','success')");
            })
            ->whereNotNull('rentals.container_id')
            ->groupBy('rentals.container_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('container:id,serial_number')
            ->get()
            ->map(fn ($r) => [
                'label' => $r->container?->serial_number ?? 'Container #'.$r->container_id,
                'value' => (float) $r->revenue,
            ])
            ->all();
    }

    private function buildFailedTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $weeks = [];
        $now = Carbon::now()->startOfWeek();
        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();
            $count = Transaction::query()
                ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['failure'])."')")
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->count();
            $amount = Transaction::query()
                ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['failure'])."')")
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->sum('amount');
            $weeks[] = [
                'label' => $start->format('d M'),
                'count' => $count,
                'amount' => (float) $amount,
            ];
        }

        return $weeks;
    }

    private function buildDailyBreakdown(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $days = [];
        $now = Carbon::now()->startOfDay();
        for ($i = 13; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $paid = Transaction::query()
                ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
                ->whereDate('transaction_date', $day)
                ->sum('amount');
            $count = Transaction::query()
                ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
                ->whereDate('transaction_date', $day)
                ->count();
            $days[] = [
                'label' => $day->format('d M'),
                'amount' => (float) $paid,
                'count' => $count,
            ];
        }

        return $days;
    }

    private function buildMetrics(): array
    {
        $total = (int) (Transaction::query()->count());
        $txGroups = FinanceStatusGroups::transactionGroups();
        $paidCount = (int) Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->count();
        $failedCount = (int) Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['failure'])."')")
            ->count();

        $totalPaidAmount = (float) Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->sum('amount');
        $avgTransaction = $paidCount > 0 ? $totalPaidAmount / $paidCount : 0;
        $successRate = $total > 0 ? round(($paidCount / $total) * 100, 1) : 0;
        $failedRate = $total > 0 ? round(($failedCount / $total) * 100, 1) : 0;

        return [
            'avgTransaction' => round($avgTransaction, 2),
            'successRate' => $successRate,
            'failedRate' => $failedRate,
        ];
    }

    private function buildYoyMom(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonth();
        $thisMonthEnd = $thisMonthStart->copy()->endOfMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();

        $thisMonth = Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->whereDate('transaction_date', '>=', $thisMonthStart)
            ->whereDate('transaction_date', '<=', $thisMonthEnd)
            ->sum('amount');
        $lastMonth = Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->whereDate('transaction_date', '>=', $lastMonthStart)
            ->whereDate('transaction_date', '<=', $lastMonthEnd)
            ->sum('amount');

        $momChange = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        $lastYearSameMonth = $thisMonthStart->copy()->subYear();
        $lastYearEnd = $lastYearSameMonth->copy()->endOfMonth();
        $lastYearAmount = Transaction::query()
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->whereDate('transaction_date', '>=', $lastYearSameMonth)
            ->whereDate('transaction_date', '<=', $lastYearEnd)
            ->sum('amount');

        $yoyChange = $lastYearAmount > 0 ? (($thisMonth - $lastYearAmount) / $lastYearAmount) * 100 : 0;

        return [
            'thisMonth' => (float) $thisMonth,
            'lastMonth' => (float) $lastMonth,
            'lastYearSameMonth' => (float) $lastYearAmount,
            'momChangePercent' => round($momChange, 1),
            'yoyChangePercent' => round($yoyChange, 1),
        ];
    }

    public function updateTransaction(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,paid,completed,succeeded,success,failed,cancelled'],
            'status_note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(in_array(strtolower((string) $request->input('status')), ['failed', 'cancelled'], true)),
            ],
        ]);

        $oldValues = [
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status_note' => $transaction->status_note,
        ];
        $transaction->status = $validated['status'];
        if (in_array(strtolower($validated['status']), ['failed', 'cancelled'], true)) {
            $transaction->status_note = $validated['status_note'] ?? null;
        } else {
            $transaction->status_note = null;
        }
        $transaction->save();
        $newValues = [
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status_note' => $transaction->status_note,
        ];

        ActivityLogService::log(
            $request->user()->id,
            'transaction_status_changed',
            'Transaction',
            $transaction->id,
            $oldValues,
            $newValues,
            "Transaction #{$transaction->id} status changed to {$transaction->status} by ".trim($request->user()->first_name.' '.$request->user()->last_name),
            $request
        );

        if (in_array($validated['status'], ['paid', 'completed', 'succeeded', 'success'], true)) {
            $rental = $transaction->rental;
            if ($rental) {
                $rental->payment_status = 'paid';
                $rental->save();
            }
        }

        if (in_array($validated['status'], ['failed', 'cancelled'], true)) {
            $rental = $transaction->rental;
            if ($rental) {
                $rental->payment_status = $validated['status'] === 'cancelled' ? 'cancelled' : 'failed';
                $rental->save();
            }
            $this->cascadeRejectRentalForNonPayment($request, $transaction->rental, 'transaction_failed');
        }

        return back()->with('status', 'Transaction updated.');
    }

    public function updateRentalPaymentStatus(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:paid,pending,unpaid,failed,cancelled'],
        ]);

        $oldValues = ['payment_status' => $rental->payment_status];
        $rental->payment_status = $validated['payment_status'];
        $rental->save();
        $newValues = ['payment_status' => $rental->payment_status];

        ActivityLogService::log(
            $request->user()->id,
            'rental_payment_status_changed',
            'Rental',
            $rental->id,
            $oldValues,
            $newValues,
            "Rental #{$rental->id} payment_status changed to {$rental->payment_status} by ".trim($request->user()->first_name.' '.$request->user()->last_name),
            $request
        );

        if (in_array($validated['payment_status'], ['failed', 'cancelled'], true)) {
            $this->cascadeRejectRentalForNonPayment($request, $rental, 'payment_cancelled');
        }

        if (strtolower($validated['payment_status']) === 'paid') {
            $rental->refresh();
            app(RentalLedgerTransactionService::class)->syncLedgerTransactionForRental($rental);
        }

        return back()->with('status', 'Rental payment status updated.');
    }

    public function approvePayment(Request $request, Rental $rental): RedirectResponse
    {
        if (! Schema::hasColumn('rentals', 'payment_approved_at')) {
            return back()->with('error', 'Payment approval is not available. Please run: php artisan migrate');
        }

        if ($rental->status !== 'approved' || $rental->payment_approved_at !== null) {
            return back()->with('error', 'Rental is not eligible for payment approval.');
        }

        $oldValues = [
            'payment_approved_at' => $rental->payment_approved_at,
            'payment_approved_by' => $rental->payment_approved_by,
            'payment_status' => $rental->payment_status,
        ];
        $rental->payment_approved_at = now();
        $rental->payment_approved_by = $request->user()->id;
        $rental->payment_status = 'paid';
        $rental->save();
        $newValues = [
            'payment_approved_at' => $rental->payment_approved_at?->toISOString(),
            'payment_approved_by' => $rental->payment_approved_by,
            'payment_status' => $rental->payment_status,
        ];

        ActivityLogService::log(
            $request->user()->id,
            'payment_approved',
            'Rental',
            $rental->id,
            $oldValues,
            $newValues,
            "Rental #{$rental->id} payment approved by ".trim($request->user()->first_name.' '.$request->user()->last_name),
            $request
        );

        $rental->refresh();
        app(RentalLedgerTransactionService::class)->syncLedgerTransactionForRental($rental);

        return back()->with('status', "Payment for rental #{$rental->id} approved.");
    }

    public function reportExport(Request $request): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'format' => ['required', 'string', 'in:csv'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $reportData = $this->buildReportData($validated['date_from'], $validated['date_to']);

        $filename = 'finance-report-'.$validated['date_from'].'-to-'.$validated['date_to'].'.csv';

        return response()->streamDownload(function () use ($reportData) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Finance report', $reportData['overview']['paidAmount'] ?? 0, $reportData['overview']['pendingAmount'] ?? 0, $reportData['overview']['failedAmount'] ?? 0]);
            fputcsv($out, ['Staff', 'Approved count', 'Approved sum', 'Commission', 'Bonus']);
            foreach ($reportData['staffStats'] ?? [] as $row) {
                fputcsv($out, [$row['name'], $row['approved_count'], $row['approved_sum'], $row['commission'], $row['bonus']]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function transactionHistory(Transaction $transaction): JsonResponse
    {
        $logs = ActivityLog::query()
            ->where('model_name', 'Transaction')
            ->where('model_id', $transaction->id)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'description' => $log->description,
                'created_at' => $log->created_at?->toISOString(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => trim(($log->user->first_name ?? '').' '.($log->user->last_name ?? '')) ?: $log->user->email,
                ] : null,
            ]);

        return response()->json(['logs' => $logs]);
    }

    public function rentalPaymentHistory(Rental $rental): JsonResponse
    {
        $logs = ActivityLog::query()
            ->where('model_name', 'Rental')
            ->where('model_id', $rental->id)
            ->where(function ($q) {
                $q->where('action', 'rental_payment_status_changed')
                    ->orWhere('action', 'payment_approved')
                    ->orWhere('action', 'like', 'status_changed_to_%')
                    ->orWhere('action', 'rental_auto_rejected_non_payment');
            })
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'description' => $log->description,
                'created_at' => $log->created_at?->toISOString(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => trim(($log->user->first_name ?? '').' '.($log->user->last_name ?? '')) ?: $log->user->email,
                ] : null,
            ]);

        return response()->json(['logs' => $logs]);
    }

    private function cascadeRejectRentalForNonPayment(Request $request, ?Rental $rental, string $trigger): void
    {
        if (! $rental || $rental->status !== 'approved') {
            return;
        }

        $oldValues = ['status' => $rental->status, 'payment_status' => $rental->payment_status];
        $rental->status = 'rejected';
        $rental->rejection_reason = self::REJECT_REASON_NON_PAYMENT;
        $rental->reviewed_by = $request->user()->id;
        $rental->reviewed_at = now();
        $rental->save();

        ActivityLogService::log(
            $request->user()->id,
            'rental_auto_rejected_non_payment',
            'Rental',
            $rental->id,
            $oldValues,
            ['status' => $rental->status, 'payment_status' => $rental->payment_status],
            "Rental #{$rental->id} auto-rejected due to non-payment (trigger: {$trigger})",
            $request
        );

        $owner = User::query()->find($rental->user_id);
        if ($owner) {
            app(NotificationService::class)->notifyUserInApp(
                $owner,
                'warning',
                "Rental #{$rental->id} cancelled",
                self::REJECT_REASON_NON_PAYMENT,
                route('rentals.center'),
            );
        }
    }
}
