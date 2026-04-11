<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\RequestContextHelper;
use Illuminate\Http\Request;

class ActivityLogService
{
    public static function log(
        int $userId,
        string $action,
        string $modelName,
        int $modelId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?Request $request = null
    ): ActivityLog {
        $request = $request ?? request();
        $ctx = $request ? RequestContextHelper::fromRequest($request) : [];

        return ActivityLog::query()->create([
            'user_id' => $userId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'action' => $action,
            'model_name' => $modelName,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'request_path' => $ctx['path'] ?? null,
            'country_code' => $ctx['country_code'] ?? null,
            'timezone' => $ctx['timezone'] ?? null,
            'gmt_offset_minutes' => $ctx['gmt_offset_minutes'] ?? null,
            'browser' => $ctx['browser'] ?? null,
            'device_type' => $ctx['device_type'] ?? null,
            'created_at' => now(),
        ]);
    }

    public static function logAuth(int $userId, string $action, ?string $description = null, ?Request $request = null): ActivityLog
    {
        return self::log($userId, $action, 'User', $userId, null, null, $description, $request ?? request());
    }
}
