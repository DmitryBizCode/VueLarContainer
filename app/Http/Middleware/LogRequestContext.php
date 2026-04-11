<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Support\RequestContextHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LogRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->logIfNeeded($request);

        return $response;
    }

    private function logIfNeeded(Request $request): void
    {
        if (! Schema::hasTable('request_logs')) {
            return;
        }

        $path = $request->path();

        // Skip assets, internal and API
        if (str_starts_with($path, '_ignition')
            || str_starts_with($path, 'api/')
            || str_starts_with($path, 'sanctum/')
            || preg_match('/\.(js|css|ico|png|jpg|jpeg|gif|svg|woff2?)$/i', $path)) {
            return;
        }

        try {
            $ctx = RequestContextHelper::fromRequest($request);

            RequestLog::query()->create([
                'user_id' => $request->user()?->id,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'path' => $ctx['path'] ?? $path,
                'method' => $request->method(),
                'ip_address' => $ctx['ip_address'] ?? null,
                'user_agent' => $ctx['user_agent'] ?? null,
                'country_code' => $ctx['country_code'] ?? null,
                'region' => $ctx['region'] ?? null,
                'city' => $ctx['city'] ?? null,
                'timezone' => $ctx['timezone'] ?? null,
                'gmt_offset_minutes' => $ctx['gmt_offset_minutes'] ?? null,
                'browser' => $ctx['browser'] ?? null,
                'browser_version' => $ctx['browser_version'] ?? null,
                'device_type' => $ctx['device_type'] ?? null,
                'platform' => $ctx['platform'] ?? null,
                'accept_language' => $ctx['accept_language'] ?? null,
                'referer' => $ctx['referer'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
