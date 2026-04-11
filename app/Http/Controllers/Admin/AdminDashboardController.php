<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RequestLog;
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
        $rentalsByStatus = DB::table('rentals')
            ->selectRaw('status, COUNT(*) as count')
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

        $finance = DB::table('transactions')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(status) IN ('paid','completed','succeeded','success') THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(status) IN ('pending','processing') THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(status) IN ('paid','completed','succeeded','success') THEN 1 END) as paid_count,
                COUNT(CASE WHEN LOWER(status) IN ('pending','processing') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(status) = 'failed' THEN 1 END) as failed_count,
                COUNT(*) as total_transactions
            ")
            ->first();

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
                'paidAmount' => (float) ($finance->paid_amount ?? 0),
                'pendingAmount' => (float) ($finance->pending_amount ?? 0),
                'failedAmount' => (float) ($finance->failed_amount ?? 0),
                'paidCount' => (int) ($finance->paid_count ?? 0),
                'pendingCount' => (int) ($finance->pending_count ?? 0),
                'failedCount' => (int) ($finance->failed_count ?? 0),
                'totalTransactions' => (int) ($finance->total_transactions ?? 0),
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
