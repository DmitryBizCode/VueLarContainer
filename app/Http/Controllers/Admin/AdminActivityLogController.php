<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\RequestContextHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'model_name' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', 'in:created_at,user_id,action,model_name,model_id'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query = ActivityLog::query()->with('user:id,first_name,last_name,email')->orderBy($sortBy, $sortOrder);

        if (! empty($validated['model_name'])) {
            $query->where('model_name', $validated['model_name']);
        }
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['action'])) {
            $query->where('action', 'like', '%'.$validated['action'].'%');
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $logs = $query->paginate(30)->withQueryString();

        $logs->getCollection()->transform(function (ActivityLog $log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user ? trim($log->user->first_name.' '.$log->user->last_name) : 'N/A',
                'user_email' => $log->user?->email,
                'action' => $log->action,
                'model_name' => $log->model_name,
                'model_id' => $log->model_id,
                'created_at' => $log->created_at?->toISOString(),
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'description' => $log->description,
                'request_path' => $log->request_path,
                'country_code' => $log->country_code,
                'timezone' => $log->timezone,
                'gmt_offset_minutes' => $log->gmt_offset_minutes,
                'browser' => $log->browser,
                'device_type' => $log->device_type,
                'device_type_label' => RequestContextHelper::deviceTypeToLabel($log->device_type),
            ];
        });

        $users = User::query()->orderBy('email')->get(['id', 'first_name', 'last_name', 'email'])->map(fn (User $u) => [
            'id' => $u->id,
            'label' => trim($u->first_name.' '.$u->last_name).' ('.$u->email.')',
        ]);

        $modelNames = ActivityLog::query()->distinct()->pluck('model_name')->filter()->values()->all();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'filters' => [
                'model_name' => $validated['model_name'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'action' => $validated['action'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'logs' => $logs,
            'users' => $users,
            'modelNames' => $modelNames,
        ]);
    }

    public function show(ActivityLog $activityLog): Response
    {
        $activityLog->load('user:id,first_name,last_name,email');

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => [
                'id' => $activityLog->id,
                'user_id' => $activityLog->user_id,
                'user_name' => $activityLog->user ? trim($activityLog->user->first_name.' '.$activityLog->user->last_name) : 'N/A',
                'user_email' => $activityLog->user?->email,
                'action' => $activityLog->action,
                'model_name' => $activityLog->model_name,
                'model_id' => $activityLog->model_id,
                'old_values' => $activityLog->old_values,
                'new_values' => $activityLog->new_values,
                'created_at' => $activityLog->created_at?->toISOString(),
                'ip_address' => $activityLog->ip_address,
                'user_agent' => $activityLog->user_agent,
                'description' => $activityLog->description,
                'request_path' => $activityLog->request_path,
                'country_code' => $activityLog->country_code,
                'timezone' => $activityLog->timezone,
                'gmt_offset_minutes' => $activityLog->gmt_offset_minutes,
                'browser' => $activityLog->browser,
                'device_type' => $activityLog->device_type,
                'device_type_label' => RequestContextHelper::deviceTypeToLabel($activityLog->device_type),
            ],
        ]);
    }
}
