<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Container;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Admin\AdminFinanceAnalyticsService;
use App\Services\Notifications\NotificationService;
use App\Services\RentalLedgerTransactionService;
use App\Support\FinancePaidRentalSubquery;
use App\Support\FinanceStatusGroups;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinanceController extends Controller
{
    private const REJECT_REASON_NON_PAYMENT = 'Non-payment for service';

    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly AdminFinanceAnalyticsService $financeAnalytics,
    ) {}

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
                FinancePaidRentalSubquery::distinctRentalIds(),
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
            'revenue_from_rentals' => (float) $this->financeAnalytics->rentalsPaidRevenueQuery()->sum('price'),
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

        $chartData = Cache::remember('admin_finance:chart_data:v1', now()->addMinutes(5), fn () => $this->financeAnalytics->buildChartData());
        $chartPaymentMethods = Cache::remember('admin_finance:chart_payment_methods:v1', now()->addMinutes(10), fn () => $this->financeAnalytics->buildChartPaymentMethods());
        $chartByRoute = Cache::remember('admin_finance:chart_by_route:v1', now()->addMinutes(10), fn () => $this->financeAnalytics->buildChartByRoute());
        $chartTopContainers = Cache::remember('admin_finance:chart_top_containers:v1', now()->addMinutes(10), fn () => $this->financeAnalytics->buildChartTopContainers());
        $failedTrend = Cache::remember('admin_finance:failed_trend:v1', now()->addMinutes(5), fn () => $this->financeAnalytics->buildFailedTrend());
        $yoyMom = Cache::remember('admin_finance:yoy_mom:v1', now()->addMinutes(10), fn () => $this->financeAnalytics->buildYoyMom());
        $dailyBreakdown = Cache::remember('admin_finance:daily_breakdown:v1', now()->addMinutes(5), fn () => $this->financeAnalytics->buildDailyBreakdown());
        $metrics = Cache::remember('admin_finance:metrics:v1', now()->addMinutes(10), fn () => $this->financeAnalytics->buildMetrics());

        $reportData = null;
        if (! empty($validated['report_date_from']) && ! empty($validated['report_date_to'])) {
            $reportData = $this->financeAnalytics->buildReportData($validated['report_date_from'], $validated['report_date_to']);
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
            'staffStats' => $this->financeAnalytics->buildStaffStats(
                $validated['date_from'] ?? null,
                $validated['date_to'] ?? null,
                $validated['staff_sort_by'] ?? 'approved_sum',
                $validated['staff_sort_order'] ?? 'desc'
            ),
        ]);
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

        $this->activityLog->log(
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

        $this->activityLog->log(
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

        $this->activityLog->log(
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

        $reportData = $this->financeAnalytics->buildReportData($validated['date_from'], $validated['date_to']);

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

        $this->activityLog->log(
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
