<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RequestLog;
use App\Support\FinanceStatusGroups;
use App\Support\PathLabelHelper;
use App\Support\RequestContextHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $txGroups = FinanceStatusGroups::transactionGroups();

        $rentalsByStatus = DB::table('rentals')
            ->selectRaw('LOWER(COALESCE(status, \'unknown\')) as status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $containersTotal = DB::table('containers')->count();
        $containersByStatus = DB::table('containers')
            ->selectRaw('current_status, COUNT(*) as count')
            ->groupBy('current_status')
            ->pluck('count', 'current_status')
            ->all();

        $routesCount = DB::table('routes')->count();
        $portsCount = DB::table('ports')->count();
        $vesselsCount = DB::table('vessels')->count();
        $ownersCount = DB::table('owners')->count();
        $usersTotal = DB::table('users')->count();
        $usersByRole = DB::table('users')
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role')
            ->all();

        $transactionsByStatus = DB::table('transactions')
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

        $successAmount = 0.0;
        $pendingAmount = 0.0;
        $failureAmount = 0.0;
        $successCount = 0;
        $pendingCount = 0;
        $failureCount = 0;
        foreach ($transactionsByStatus as $status => $row) {
            if (in_array($status, $txGroups['success'], true)) {
                $successAmount += (float) ($row['amount_sum'] ?? 0);
                $successCount += (int) ($row['count'] ?? 0);
            } elseif (in_array($status, $txGroups['pending'], true)) {
                $pendingAmount += (float) ($row['amount_sum'] ?? 0);
                $pendingCount += (int) ($row['count'] ?? 0);
            } elseif (in_array($status, $txGroups['failure'], true)) {
                $failureAmount += (float) ($row['amount_sum'] ?? 0);
                $failureCount += (int) ($row['count'] ?? 0);
            }
        }
        $totalTransactions = array_sum(array_map(fn ($r) => (int) ($r['count'] ?? 0), $transactionsByStatus));

        $syntheticPendingApproval = DB::table('rentals')
            ->whereRaw('LOWER(status) = ?', ['pending_approval'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            });
        $syntheticPendingApprovalAmount = (float) $syntheticPendingApproval->sum('price');
        $syntheticPendingApprovalCount = (int) $syntheticPendingApproval->count();

        $rentalsByPaymentStatus = DB::table('rentals')
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
                ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
                ->sum('transactions.amount'),
        ];

        $activityLogsToday = DB::table('activity_logs')
            ->whereDate('created_at', today())
            ->count();
        $activityLogsTotal = DB::table('activity_logs')->count();

        $requestLogsToday = 0;
        $requestLogsTotal = 0;
        $topCountries = [];
        $topDevicesOrdered = [];
        $topBrowsersOrdered = [];
        $popularPathsUser = [];
        $popularPathsAdmin = [];
        if (Schema::hasTable('request_logs')) {
            $requestLogsToday = RequestLog::query()->whereDate('created_at', today())->count();
            $requestLogsTotal = RequestLog::query()->count();
            $period = [now()->subDays(30)->toDateString(), now()->toDateString()];
            $base = RequestLog::query()->whereBetween('created_at', [$period[0].' 00:00:00', $period[1].' 23:59:59']);

            $topCountries = (clone $base)
                ->whereNotNull('country_code')
                ->selectRaw('country_code, COUNT(*) as cnt')
                ->groupBy('country_code')
                ->orderByDesc('cnt')
                ->limit(10)
                ->pluck('cnt', 'country_code')
                ->all();

            $topDevicesOrdered = (clone $base)
                ->whereNotNull('device_type')
                ->selectRaw('device_type, COUNT(*) as cnt')
                ->groupBy('device_type')
                ->orderByDesc('cnt')
                ->pluck('device_type')
                ->values()
                ->map(fn ($t) => RequestContextHelper::deviceTypeToLabel($t))
                ->all();

            $topBrowsersOrdered = (clone $base)
                ->whereNotNull('browser')
                ->selectRaw('browser, COUNT(*) as cnt')
                ->groupBy('browser')
                ->orderByDesc('cnt')
                ->limit(10)
                ->pluck('browser')
                ->values()
                ->all();

            $pathsWithCount = (clone $base)
                ->selectRaw('path, COUNT(*) as cnt')
                ->groupBy('path')
                ->orderByDesc('cnt')
                ->get();

            $userPaths = [];
            $adminPaths = [];
            foreach ($pathsWithCount as $r) {
                $label = PathLabelHelper::pathToLabel($r->path);
                if (PathLabelHelper::isAdminPath($r->path)) {
                    $adminPaths[$label] = ($adminPaths[$label] ?? 0) + $r->cnt;
                } else {
                    $userPaths[$label] = ($userPaths[$label] ?? 0) + $r->cnt;
                }
            }
            arsort($userPaths);
            arsort($adminPaths);
            $popularPathsUser = array_keys(array_slice($userPaths, 0, 10, true));
            $popularPathsAdmin = array_keys(array_slice($adminPaths, 0, 10, true));
        }

        $pendingApprovals = Rental::query()
            ->with(['user', 'container', 'originPort.country', 'destinationPort.country'])
            ->where('status', 'pending_approval')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Rental $r) => [
                'id' => $r->id,
                'customer' => trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? '')),
                'container_serial' => $r->container?->serial_number,
                'origin' => $r->originPort?->name,
                'destination' => $r->destinationPort?->name,
                'price' => (float) $r->price,
                'created_at' => $r->created_at,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'rentalsByStatus' => $rentalsByStatus,
                'containersTotal' => $containersTotal,
                'containersByStatus' => $containersByStatus,
                'routesCount' => $routesCount,
                'portsCount' => $portsCount,
                'vesselsCount' => $vesselsCount,
                'ownersCount' => $ownersCount,
                'usersTotal' => $usersTotal,
                'usersByRole' => $usersByRole,
                'paidAmount' => (float) $successAmount,
                'pendingAmount' => (float) ($pendingAmount + $syntheticPendingApprovalAmount),
                'failedAmount' => (float) ($failureAmount + $rejectedApproval['lostRevenuePriceSum']),
                'paidCount' => (int) $successCount,
                'pendingCount' => (int) ($pendingCount + $syntheticPendingApprovalCount),
                'failedCount' => (int) ($failureCount + $rejectedApproval['count']),
                'totalTransactions' => (int) ($totalTransactions + $syntheticPendingApprovalCount),
                'transactionsByStatus' => $transactionsByStatus,
                'rentalsByPaymentStatus' => $rentalsByPaymentStatus,
                'rejectedApproval' => $rejectedApproval,
                'activityLogsToday' => $activityLogsToday,
                'activityLogsTotal' => $activityLogsTotal,
                'requestLogsToday' => $requestLogsToday,
                'requestLogsTotal' => $requestLogsTotal,
                'topCountries' => $topCountries,
                'topDevicesOrdered' => $topDevicesOrdered,
                'topBrowsersOrdered' => $topBrowsersOrdered,
                'popularPathsUser' => $popularPathsUser,
                'popularPathsAdmin' => $popularPathsAdmin,
            ],
            'pendingApprovals' => $pendingApprovals,
        ]);
    }
}
