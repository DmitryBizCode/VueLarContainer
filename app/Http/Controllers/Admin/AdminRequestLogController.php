<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\RequestLog;
use App\Models\User;
use App\Support\PathLabelHelper;
use App\Support\RequestContextHelper;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminRequestLogController extends Controller
{
    /**
     * Request logs list with filters + analytics summary.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'path' => ['nullable', 'string', 'max:200'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'device_type' => ['nullable', 'string', 'in:desktop,desktop_windows,desktop_mac,desktop_linux,mobile,tablet'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', 'in:created_at,path,country_code,device_type'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Keep URL clean: redirect ?page=1 to same URL without page
        if ((int) $request->query('page', 1) === 1 && $request->has('page')) {
            return redirect()->route('admin.request-logs.index', $request->except('page'));
        }

        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';
        $query = RequestLog::query()->with('user:id,first_name,last_name,email')->orderBy($sort, $order);

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['path'])) {
            $query->where('path', 'like', '%'.$validated['path'].'%');
        }
        if (! empty($validated['country_code'])) {
            $query->where('country_code', $validated['country_code']);
        }
        if (! empty($validated['device_type'])) {
            $query->where('device_type', $validated['device_type']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $logs = $query->paginate($perPage)->withQueryString();

        $logs->getCollection()->transform(function (RequestLog $log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user ? trim($log->user->first_name.' '.$log->user->last_name) : null,
                'user_email' => $log->user?->email,
                'path' => $log->path,
                'method' => $log->method,
                'ip_address' => $log->ip_address,
                'country_code' => $log->country_code,
                'region' => $log->region,
                'city' => $log->city,
                'timezone' => $log->timezone,
                'gmt_offset_minutes' => $log->gmt_offset_minutes,
                'browser' => $log->browser,
                'browser_version' => $log->browser_version,
                'device_type' => $log->device_type,
                'device_type_label' => RequestContextHelper::deviceTypeToLabel($log->device_type),
                'platform' => $log->platform,
                'accept_language' => $log->accept_language,
                'referer' => $log->referer,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
            ];
        });

        $users = User::query()->orderBy('email')->get(['id', 'first_name', 'last_name', 'email'])->map(fn (User $u) => [
            'id' => $u->id,
            'label' => trim($u->first_name.' '.$u->last_name).' ('.$u->email.')',
        ]);

        $analytics = $this->analytics($request);

        return Inertia::render('Admin/RequestLogs/Index', [
            'filters' => [
                'user_id' => $validated['user_id'] ?? null,
                'path' => $validated['path'] ?? null,
                'country_code' => $validated['country_code'] ?? null,
                'device_type' => $validated['device_type'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'sort' => $sort,
                'order' => $order,
            ],
            'logs' => $logs,
            'users' => $users,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Full chain for one user: request_logs + activity_logs merged by created_at (blockchain-style).
     */
    public function userChain(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $requestLogs = RequestLog::query()
            ->where('user_id', $user->id)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->map(fn (RequestLog $r) => [
                'type' => 'request',
                'id' => 'r'.$r->id,
                'created_at' => $r->created_at?->toISOString(),
                'path' => $r->path,
                'method' => $r->method,
                'country_code' => $r->country_code,
                'region' => $r->region,
                'city' => $r->city,
                'timezone' => $r->timezone,
                'gmt_offset_minutes' => $r->gmt_offset_minutes,
                'browser' => $r->browser,
                'browser_version' => $r->browser_version,
                'device_type' => $r->device_type,
                'device_type_label' => RequestContextHelper::deviceTypeToLabel($r->device_type),
                'platform' => $r->platform,
                'accept_language' => $r->accept_language,
                'referer' => $r->referer,
                'ip_address' => $r->ip_address,
            ]);

        $activityLogs = ActivityLog::query()
            ->where('user_id', $user->id)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->map(fn (ActivityLog $a) => [
                'type' => 'activity',
                'id' => 'a'.$a->id,
                'created_at' => $a->created_at?->toISOString(),
                'action' => $a->action,
                'model_name' => $a->model_name,
                'model_id' => $a->model_id,
                'description' => $a->description,
                'request_path' => $a->request_path,
                'country_code' => $a->country_code,
                'timezone' => $a->timezone,
                'gmt_offset_minutes' => $a->gmt_offset_minutes,
                'browser' => $a->browser,
                'device_type' => $a->device_type,
                'device_type_label' => RequestContextHelper::deviceTypeToLabel($a->device_type),
                'ip_address' => $a->ip_address,
            ]);

        $chain = collect($requestLogs)->concat($activityLogs)
            ->sortByDesc(fn ($i) => $i['created_at'])
            ->values()
            ->take(500)
            ->all();

        $chainDaily = $this->buildUserChainDailySeries($user->id, $dateFrom, $dateTo);

        return Inertia::render('Admin/RequestLogs/UserChain', [
            'user' => [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
            ],
            'chain' => $chain,
            'chain_daily' => $chainDaily,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    /**
     * Full calendar-day counts for the chart (not limited by the 500-row timeline cap).
     *
     * @return list<array{day: string, pages: int, activities: int}>
     */
    private function buildUserChainDailySeries(int $userId, ?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom === null || $dateTo === null || $dateFrom === '' || $dateTo === '') {
            return [];
        }

        $startDay = CarbonImmutable::parse($dateFrom)->startOfDay();
        $endDay = CarbonImmutable::parse($dateTo)->startOfDay();
        if ($endDay->lt($startDay)) {
            return [];
        }

        $span = (int) $startDay->diffInDays($endDay) + 1;
        if ($span > 400) {
            $startDay = $endDay->subDays(399);
        }

        $rangeStart = $startDay->startOfDay();
        $rangeEnd = $endDay->endOfDay();

        $dayExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => '(created_at AT TIME ZONE \'UTC\')::date',
            'sqlite' => 'date(created_at)',
            default => 'DATE(created_at)',
        };

        $pagesByDay = $this->pluckDailyCounts(
            RequestLog::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd]),
            $dayExpr
        );

        $activitiesByDay = $this->pluckDailyCounts(
            ActivityLog::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd]),
            $dayExpr
        );

        $series = [];
        foreach (CarbonPeriod::create($startDay->toDateString(), $endDay->toDateString()) as $day) {
            $key = $day->toDateString();
            $series[] = [
                'day' => $key,
                'pages' => (int) ($pagesByDay[$key] ?? 0),
                'activities' => (int) ($activitiesByDay[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<string, int>
     */
    private function pluckDailyCounts(Builder $query, string $dayExpr): array
    {
        $rows = (clone $query)
            ->selectRaw($dayExpr.' as day_bucket, COUNT(*) as c')
            ->groupBy(DB::raw($dayExpr))
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $raw = $row->day_bucket;
            if ($raw instanceof \DateTimeInterface) {
                $key = $raw->format('Y-m-d');
            } else {
                $key = substr((string) $raw, 0, 10);
            }
            $map[$key] = (int) $row->c;
        }

        return $map;
    }

    /**
     * Analytics from request_logs for dashboard / diagnostics.
     */
    public function analytics(Request $request): array
    {
        $dateFrom = $request->input('date_from') ?: now()->subDays(30)->toDateString();
        $dateTo = $request->input('date_to') ?: now()->toDateString();

        $base = RequestLog::query()->whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);

        $topCountries = (clone $base)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as cnt')
            ->groupBy('country_code')
            ->orderByDesc('cnt')
            ->limit(15)
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
        $popularPathsUser = array_keys(array_slice($userPaths, 0, 15, true));
        $popularPathsAdmin = array_keys(array_slice($adminPaths, 0, 15, true));

        $totalRequests = (clone $base)->count();
        $uniqueSessions = (int) (clone $base)->selectRaw('COUNT(DISTINCT session_id) as c')->value('c');
        $withUser = (clone $base)->whereNotNull('user_id')->count();

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_requests' => $totalRequests,
            'unique_sessions' => $uniqueSessions,
            'authenticated_requests' => $withUser,
            'top_countries' => $topCountries,
            'top_devices_ordered' => $topDevicesOrdered,
            'top_browsers_ordered' => $topBrowsersOrdered,
            'popular_paths_user' => $popularPathsUser,
            'popular_paths_admin' => $popularPathsAdmin,
        ];
    }
}
