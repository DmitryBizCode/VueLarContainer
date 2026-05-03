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
    public function buildStaffStats(?string $dateFrom, ?string $dateTo, string $sortBy, string $sortOrder): array
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
        $users = $userIds ? User::query()->whereIn('id', $userIds)->get()->keyBy('id') : collect();

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
    public function rentalsPaidRevenueQuery()
    {
        $paidRentalIds = FinancePaidRentalSubquery::distinctRentalIds()->pluck('rental_id');

        return Rental::query()->where(function ($q) use ($paidRentalIds) {
            $q->whereRaw('LOWER(payment_status) = ?', ['paid'])
                ->orWhereIn('id', $paidRentalIds);
        });
    }

    public function buildChartData(): array
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
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS).' THEN amount ELSE 0 END), 0) as paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_PENDING).' THEN amount ELSE 0 END), 0) as pending')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_FAILURE).' THEN amount ELSE 0 END), 0) as failed')
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
            ->whereRaw(FinanceStatusGroups::sqlLowerStatusIn('transactions.status', FinanceStatusGroups::TRANSACTION_SUCCESS))
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
            ->select('rentals.route_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereIn('rentals.id', function ($q) {
                $q->select('rental_id')
                    ->from('transactions')
                    ->whereRaw(FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS));
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
            ->select('rentals.container_id')
            ->selectRaw('COALESCE(SUM(rentals.price), 0) as revenue')
            ->whereIn('rentals.id', function ($q) {
                $q->select('rental_id')
                    ->from('transactions')
                    ->whereRaw(FinanceStatusGroups::sqlLowerStatusIn('status', FinanceStatusGroups::TRANSACTION_SUCCESS));
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
        for ($i = 11; $i >= 0; $i--) {
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
