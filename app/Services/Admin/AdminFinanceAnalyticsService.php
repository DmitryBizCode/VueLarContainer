<?php

namespace App\Services\Admin;

use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinancePaidRentalSubquery;
use App\Support\FinanceStatusGroups;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminFinanceAnalyticsService
{
    public function buildSuccessRateTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $weeks = [];
        $now = Carbon::now()->startOfWeek();
        for ($i = 25; $i >= 0; $i--) {
            $start = $now->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            $total = (int) Transaction::query()
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->count();
            $success = (int) Transaction::query()
                ->whereRaw($successSql)
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->count();
            $rate = $total > 0 ? ($success / $total) * 100 : 0;
            $weeks[] = ['label' => $start->format('d M'), 'value' => round($rate, 1)];
        }

        return $weeks;
    }

    public function buildAvgTicketTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $weeks = [];
        $now = Carbon::now()->startOfWeek();
        for ($i = 25; $i >= 0; $i--) {
            $start = $now->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            $amount = (float) Transaction::query()
                ->whereRaw($successSql)
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->sum('amount');
            $count = (int) Transaction::query()
                ->whereRaw($successSql)
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->count();
            $avg = $count > 0 ? $amount / $count : 0;
            $weeks[] = ['label' => $start->format('d M'), 'value' => round($avg, 2)];
        }

        return $weeks;
    }

    public function buildPaymentMethodTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $start = Carbon::now()->startOfMonth()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('LOWER(COALESCE(payment_method, \'unknown\')) as method, COALESCE(SUM(amount), 0) as value')
            ->groupBy('method')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->method, 'value' => (float) $r->value])
            ->all();
    }

    public function buildRouteTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('transactions.status', $txGroups['success']);
        $start = Carbon::now()->startOfMonth()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $labelExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => "COALESCE(o.name, '—') || ' → ' || COALESCE(d.name, '—')",
            default => "CONCAT(COALESCE(o.name, '—'), ' → ', COALESCE(d.name, '—'))",
        };

        return Transaction::query()
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->leftJoin('ports as o', 'o.id', '=', 'rentals.origin_port_id')
            ->leftJoin('ports as d', 'd.id', '=', 'rentals.destination_port_id')
            ->whereRaw($successSql)
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->selectRaw($labelExpr.' as label')
            ->selectRaw('COALESCE(SUM(transactions.amount), 0) as value')
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (float) $r->value])
            ->all();
    }

    private function percentileFromSorted(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted[0];
        }

        $p = max(0.0, min(1.0, $p));
        $pos = ($n - 1) * $p;
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);
        if ($lo === $hi) {
            return (float) $sorted[$lo];
        }
        $w = $pos - $lo;

        return (float) $sorted[$lo] * (1 - $w) + (float) $sorted[$hi] * $w;
    }

    public function buildKpiFormulas(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $now = Carbon::now();

        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();

        $thisMonthAmount = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereDate('transaction_date', '>=', $thisMonthStart)
            ->whereDate('transaction_date', '<=', $thisMonthEnd)
            ->sum('amount');
        $thisMonthCount = (int) Transaction::query()
            ->whereRaw($successSql)
            ->whereDate('transaction_date', '>=', $thisMonthStart)
            ->whereDate('transaction_date', '<=', $thisMonthEnd)
            ->count();
        $avgTicketThisMonth = $thisMonthCount > 0 ? $thisMonthAmount / $thisMonthCount : 0.0;

        $rolling30Start = $now->copy()->subDays(29)->startOfDay();
        $rolling30End = $now->copy()->endOfDay();
        $rolling30Amount = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$rolling30Start, $rolling30End])
            ->sum('amount');

        $ytdStart = $now->copy()->startOfYear();
        $ytdAmount = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$ytdStart, $rolling30End])
            ->sum('amount');

        $quarterStart = $now->copy()->firstOfQuarter()->startOfDay();
        $prevQuarterStart = $quarterStart->copy()->subQuarter();
        $prevQuarterEnd = $quarterStart->copy()->subSecond();

        $thisQuarterAmount = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$quarterStart, $rolling30End])
            ->sum('amount');
        $prevQuarterAmount = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$prevQuarterStart, $prevQuarterEnd])
            ->sum('amount');
        $qoqChange = $prevQuarterAmount > 0 ? (($thisQuarterAmount - $prevQuarterAmount) / $prevQuarterAmount) * 100 : 0.0;

        $total30 = (int) Transaction::query()
            ->whereBetween('transaction_date', [$rolling30Start, $rolling30End])
            ->count();
        $success30 = (int) Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$rolling30Start, $rolling30End])
            ->count();
        $successRate30 = $total30 > 0 ? ($success30 / $total30) * 100 : 0.0;

        $amounts30 = Transaction::query()
            ->whereRaw($successSql)
            ->whereBetween('transaction_date', [$rolling30Start, $rolling30End])
            ->orderBy('amount')
            ->pluck('amount')
            ->map(fn ($v) => (float) $v)
            ->all();
        $p95Ticket30 = $this->percentileFromSorted($amounts30, 0.95);

        $monthsStart = $now->copy()->startOfMonth()->subMonths(11)->startOfMonth()->toDateString();
        $monthsEnd = $now->copy()->endOfMonth()->toDateString();
        $paidTx12 = (float) Transaction::query()
            ->whereRaw($successSql)
            ->whereDate('transaction_date', '>=', $monthsStart)
            ->whereDate('transaction_date', '<=', $monthsEnd)
            ->sum('amount');
        $earnedQuery12 = $this->rentalsPaidRevenueQuery();
        $closedAtExpr = 'COALESCE(rentals.end_date, rentals.updated_at)';
        match (DB::connection()->getDriverName()) {
            'pgsql' => $earnedQuery12
                ->whereRaw("{$closedAtExpr}::date >= ?", [$monthsStart])
                ->whereRaw("{$closedAtExpr}::date <= ?", [$monthsEnd]),
            'sqlite' => $earnedQuery12
                ->whereRaw("date({$closedAtExpr}) >= ?", [$monthsStart])
                ->whereRaw("date({$closedAtExpr}) <= ?", [$monthsEnd]),
            default => $earnedQuery12
                ->whereRaw("DATE({$closedAtExpr}) >= ?", [$monthsStart])
                ->whereRaw("DATE({$closedAtExpr}) <= ?", [$monthsEnd]),
        };
        $earned12 = (float) $earnedQuery12->sum('price');
        $gapCollectedMinusEarned12 = $paidTx12 - $earned12;

        return [
            'avgTicketThisMonth' => round($avgTicketThisMonth, 2),
            'p95Ticket30d' => round($p95Ticket30, 2),
            'successRate30d' => round($successRate30, 1),
            'rolling30dAmount' => round($rolling30Amount, 2),
            'ytdAmount' => round($ytdAmount, 2),
            'qoqChangePercent' => round($qoqChange, 1),
            'gapCollectedMinusEarned12m' => round($gapCollectedMinusEarned12, 2),
            // extra raws for tooltips/debug if needed
            'thisMonthAmount' => round($thisMonthAmount, 2),
            'thisQuarterAmount' => round($thisQuarterAmount, 2),
            'prevQuarterAmount' => round($prevQuarterAmount, 2),
        ];
    }

    public function buildStaffStats(?string $dateFrom, ?string $dateTo, string $sortBy, string $sortOrder): array
    {
        $reviewQuery = Rental::query()
            ->whereNotNull('reviewed_by')
            ->where('status', 'approved')
            ->selectRaw('reviewed_by as user_id, COUNT(*) as rental_review_count, COALESCE(SUM(price), 0) as rental_review_sum');

        if ($dateFrom) {
            $reviewQuery->whereDate('rentals.reviewed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $reviewQuery->whereDate('rentals.reviewed_at', '<=', $dateTo);
        }
        $reviewQuery->groupBy('reviewed_by');
        $reviewRows = $reviewQuery->get()->keyBy('user_id');

        $paymentQuery = Rental::query()
            ->whereNotNull('payment_approved_by')
            ->whereNotNull('payment_approved_at')
            ->selectRaw('payment_approved_by as user_id, COUNT(*) as payment_auth_count, COALESCE(SUM(price), 0) as payment_auth_sum');

        if ($dateFrom) {
            $paymentQuery->whereDate('rentals.payment_approved_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $paymentQuery->whereDate('rentals.payment_approved_at', '<=', $dateTo);
        }
        $paymentQuery->groupBy('payment_approved_by');
        $paymentRows = $paymentQuery->get()->keyBy('user_id');

        $userIds = $reviewRows->keys()->merge($paymentRows->keys())->unique()->filter()->values()->all();
        $users = $userIds !== [] ? User::query()->whereIn('id', $userIds)->get()->keyBy('id') : collect();

        $result = collect($userIds)->map(function ($userId) use ($reviewRows, $paymentRows, $users) {
            $r = $reviewRows->get($userId);
            $p = $paymentRows->get($userId);
            $rentalReviewCount = (int) ($r->rental_review_count ?? 0);
            $rentalReviewSum = (float) ($r->rental_review_sum ?? 0);
            $paymentAuthCount = (int) ($p->payment_auth_count ?? 0);
            $paymentAuthSum = (float) ($p->payment_auth_sum ?? 0);

            $user = $users->get($userId);
            $rate = $user ? (float) ($user->commission_rate ?? 0) : 0;
            $commission = $rentalReviewSum * $rate / 100;
            $bonusType = $user?->bonus_type ?? null;
            $bonusVal = $user ? (float) ($user->bonus_value ?? 0) : 0;
            $bonus = 0.0;
            if ($bonusType === 'fixed') {
                $bonus = $bonusVal;
            } elseif ($bonusType === 'percent') {
                $bonus = $rentalReviewSum * $bonusVal / 100;
            }

            return [
                'user_id' => $userId,
                'name' => $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email : 'User #'.$userId,
                'email' => $user?->email ?? null,
                'rental_review_count' => $rentalReviewCount,
                'rental_review_sum' => round($rentalReviewSum, 2),
                'payment_auth_count' => $paymentAuthCount,
                'payment_auth_sum' => round($paymentAuthSum, 2),
                'approved_count' => $rentalReviewCount,
                'approved_sum' => round($rentalReviewSum, 2),
                'commission_rate' => $rate,
                'commission' => round($commission, 2),
                'bonus_type' => $bonusType,
                'bonus_value' => $bonusVal,
                'bonus' => round($bonus, 2),
            ];
        });

        $sortKey = match ($sortBy) {
            'approved_count' => 'rental_review_count',
            'approved_sum' => 'rental_review_sum',
            default => $sortBy,
        };
        $allowedSort = [
            'name', 'rental_review_count', 'rental_review_sum', 'payment_auth_count', 'payment_auth_sum', 'commission', 'bonus',
        ];
        if (in_array($sortKey, $allowedSort, true)) {
            $result = $sortOrder === 'asc'
                ? $result->sortBy($sortKey === 'name' ? 'name' : $sortKey)
                : $result->sortByDesc($sortKey === 'name' ? 'name' : $sortKey);
        }

        return $result->values()->all();
    }

    /**
     * @return array{overview: array<string, mixed>, staffStats: array<int, mixed>}
     */
    public function buildReportData(string $dateFrom, string $dateTo): array
    {
        $overview = Transaction::query()
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS).' THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_PENDING).' THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_FAILURE).' THEN amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS).' THEN 1 END) as paid_count,
                COUNT(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_PENDING).' THEN 1 END) as pending_count,
                COUNT(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_FAILURE).' THEN 1 END) as failed_count,
                COUNT(*) as total_transactions
            ')->first();

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

        $awaitingCapture = Rental::query()
            ->whereNotNull('payment_approved_at')
            ->whereRaw("LOWER(payment_status) IN ('pending', 'unpaid')")
            ->whereDate('payment_approved_at', '>=', $dateFrom)
            ->whereDate('payment_approved_at', '<=', $dateTo)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(price), 0) as amount')
            ->first();

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
            'awaitingCaptureSummary' => [
                'count' => (int) ($awaitingCapture->count ?? 0),
                'amount' => (float) ($awaitingCapture->amount ?? 0),
            ],
            'staffStats' => $this->buildStaffStats($dateFrom, $dateTo, 'approved_sum', 'desc'),
        ];
    }

    /** Completed rentals recognized as paid: payment_status = paid OR at least one successful transaction. */
    public function rentalsPaidRevenueQuery()
    {
        $paidRentalIds = FinancePaidRentalSubquery::distinctRentalIds()->pluck('rental_id');

        return Rental::query()
            ->whereRaw('LOWER(rentals.status) = ?', ['completed'])
            ->where(function ($q) use ($paidRentalIds) {
                $q->whereRaw('LOWER(payment_status) = ?', ['paid'])
                    ->orWhereIn('id', $paidRentalIds);
            });
    }

    public function buildChartData(): array
    {
        $months = [];
        $now = Carbon::now()->startOfMonth();
        // 24 rolling calendar months — avoids empty charts when demo data predates a 12‑month window.
        for ($i = 23; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i);
            $end = $start->copy()->endOfMonth();
            $months[] = [
                'label' => $start->format('M Y'),
                'key' => $start->format('Y-m'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        $ymExpr = $this->sqlYearMonthExpr('transaction_date');

        // Avoid keeping models' default `select *` — PostgreSQL rejects GROUP BY when extra columns are selected.
        $txRows = Transaction::query()
            ->select(DB::raw($ymExpr.' as ym'))
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS).' THEN amount ELSE 0 END), 0) as paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_PENDING).' THEN amount ELSE 0 END), 0) as pending')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_FAILURE).' THEN amount ELSE 0 END), 0) as failed')
            ->whereDate('transaction_date', '>=', $months[0]['start'])
            ->whereDate('transaction_date', '<=', $months[count($months) - 1]['end'])
            ->groupByRaw($ymExpr)
            ->get()
            ->keyBy('ym');

        $createdYmExpr = $this->sqlYearMonthExpr('created_at');
        $rentalCounts = Rental::query()
            ->select(DB::raw($createdYmExpr.' as ym'))
            ->selectRaw('COUNT(*) as rentalCount')
            ->whereDate('created_at', '>=', $months[0]['start'])
            ->whereDate('created_at', '<=', $months[count($months) - 1]['end'])
            ->groupByRaw($createdYmExpr)
            ->get()
            ->keyBy('ym');

        $closedYmExpr = $this->sqlYearMonthExpr('COALESCE(rentals.end_date, rentals.updated_at)');
        $rangeStart = $months[0]['start'];
        $rangeEnd = $months[count($months) - 1]['end'];
        $closedAtExpr = 'COALESCE(rentals.end_date, rentals.updated_at)';

        $paidRentalRevenue = Rental::query()
            ->leftJoinSub(FinancePaidRentalSubquery::distinctRentalIds(), 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
            ->whereRaw('LOWER(rentals.status) = ?', ['completed'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                    ->orWhereNotNull('paid_tx.rental_id');
            });

        match (DB::connection()->getDriverName()) {
            'pgsql' => $paidRentalRevenue
                ->whereRaw("{$closedAtExpr}::date >= ?", [$rangeStart])
                ->whereRaw("{$closedAtExpr}::date <= ?", [$rangeEnd]),
            'sqlite' => $paidRentalRevenue
                ->whereRaw("date({$closedAtExpr}) >= ?", [$rangeStart])
                ->whereRaw("date({$closedAtExpr}) <= ?", [$rangeEnd]),
            default => $paidRentalRevenue
                ->whereRaw("DATE({$closedAtExpr}) >= ?", [$rangeStart])
                ->whereRaw("DATE({$closedAtExpr}) <= ?", [$rangeEnd]),
        };

        $paidRentalRevenue
            ->select(DB::raw($closedYmExpr.' as ym'))
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as rentalRevenue')
            ->groupByRaw($closedYmExpr);

        $paidRentalRevenue = $paidRentalRevenue->get()->keyBy('ym');

        $updatedYmExpr = $this->sqlYearMonthExpr('rentals.updated_at');
        $rangeStartChart = $months[0]['start'];
        $rangeEndChart = $months[count($months) - 1]['end'];

        $rejectedRentalByMonth = Rental::query()
            ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
            ->whereDate('rentals.updated_at', '>=', $rangeStartChart)
            ->whereDate('rentals.updated_at', '<=', $rangeEndChart)
            ->select(DB::raw($updatedYmExpr.' as ym'))
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as add_failed')
            ->groupByRaw($updatedYmExpr)
            ->get()
            ->keyBy('ym');

        $pendingApprovalNoTxByMonth = Rental::query()
            ->whereRaw('LOWER(rentals.status) = ?', ['pending_approval'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->whereDate('rentals.updated_at', '>=', $rangeStartChart)
            ->whereDate('rentals.updated_at', '<=', $rangeEndChart)
            ->select(DB::raw($updatedYmExpr.' as ym'))
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as add_pending')
            ->groupByRaw($updatedYmExpr)
            ->get()
            ->keyBy('ym');

        return array_map(function (array $m) use ($txRows, $rentalCounts, $paidRentalRevenue, $rejectedRentalByMonth, $pendingApprovalNoTxByMonth) {
            $ym = $m['key'];
            $tx = $txRows->get($ym);
            $rc = $rentalCounts->get($ym);
            $rr = $paidRentalRevenue->get($ym);
            $rej = $rejectedRentalByMonth->get($ym);
            $pnd = $pendingApprovalNoTxByMonth->get($ym);

            return [
                'label' => $m['label'],
                'paid' => (float) ($tx->paid ?? 0),
                'pending' => (float) ($tx->pending ?? 0) + (float) ($pnd->add_pending ?? 0),
                'failed' => (float) ($tx->failed ?? 0) + (float) ($rej->add_failed ?? 0),
                'rentalRevenue' => (float) ($rr->rentalRevenue ?? 0),
                'rentalCount' => (int) ($rc->rentalCount ?? 0),
            ];
        }, $months);
    }

    public function sqlYearMonthExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        if ($driver === 'pgsql') {
            return "to_char({$column}, 'YYYY-MM')";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function buildChartPaymentMethods(): array
    {
        $rows = Transaction::query()
            ->selectRaw('LOWER(COALESCE(payment_method, \'unknown\')) as method, SUM(amount) as total')
            ->whereRaw(FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS))
            ->groupBy(DB::raw('LOWER(COALESCE(payment_method, \'unknown\'))'))
            ->get();

        return $rows->map(fn ($r) => ['label' => ucfirst($r->method), 'value' => (float) $r->total])->all();
    }

    public function buildChartByRoute(): array
    {
        $rows = Rental::query()
            ->leftJoinSub(FinancePaidRentalSubquery::distinctRentalIds(), 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
            ->select('rentals.route_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereRaw('LOWER(rentals.status) = ?', ['completed'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                    ->orWhereNotNull('paid_tx.rental_id');
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

    public function buildChartTopContainers(): array
    {
        return Rental::query()
            ->leftJoinSub(FinancePaidRentalSubquery::distinctRentalIds(), 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
            ->select('rentals.container_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereRaw('LOWER(rentals.status) = ?', ['completed'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                    ->orWhereNotNull('paid_tx.rental_id');
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

    public function buildFailedTrend(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $weeks = [];
        $now = Carbon::now()->startOfWeek();
        for ($i = 25; $i >= 0; $i--) {
            $start = $now->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();
            $failureSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['failure']);
            $count = Transaction::query()
                ->whereRaw($failureSql)
                ->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end)
                ->count();
            $amount = Transaction::query()
                ->whereRaw($failureSql)
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

    public function buildDailyBreakdown(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $days = [];
        $now = Carbon::now()->startOfDay();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        for ($i = 13; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $paid = Transaction::query()
                ->whereRaw($successSql)
                ->whereDate('transaction_date', $day)
                ->sum('amount');
            $count = Transaction::query()
                ->whereRaw($successSql)
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

    public function buildMetrics(): array
    {
        $total = (int) (Transaction::query()->count());
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $failureSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['failure']);
        $paidCount = (int) Transaction::query()
            ->whereRaw($successSql)
            ->count();
        $failedCount = (int) Transaction::query()
            ->whereRaw($failureSql)
            ->count();

        $totalPaidAmount = (float) Transaction::query()
            ->whereRaw($successSql)
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

    public function buildYoyMom(): array
    {
        $txGroups = FinanceStatusGroups::transactionGroups();
        $successSql = FinanceStatusGroups::sqlLowerStatusIn('status', $txGroups['success']);
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonth();
        $thisMonthEnd = $thisMonthStart->copy()->endOfMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();

        $thisMonth = Transaction::query()
            ->whereRaw($successSql)
            ->whereDate('transaction_date', '>=', $thisMonthStart)
            ->whereDate('transaction_date', '<=', $thisMonthEnd)
            ->sum('amount');
        $lastMonth = Transaction::query()
            ->whereRaw($successSql)
            ->whereDate('transaction_date', '>=', $lastMonthStart)
            ->whereDate('transaction_date', '<=', $lastMonthEnd)
            ->sum('amount');

        $momChange = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        $lastYearSameMonth = $thisMonthStart->copy()->subYear();
        $lastYearEnd = $lastYearSameMonth->copy()->endOfMonth();
        $lastYearAmount = Transaction::query()
            ->whereRaw($successSql)
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
}
