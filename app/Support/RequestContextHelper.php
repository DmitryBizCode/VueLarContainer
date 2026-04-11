<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RequestContextHelper
{
    public static function fromRequest(Request $request): array
    {
        $ua = $request->userAgent() ?? '';
        $browser = self::parseBrowser($ua);
        $platform = self::parsePlatform($ua);
        $device = self::parseDeviceType($ua, $platform);

        $timezone = $request->header('X-Timezone');
        $gmtOffset = $request->header('X-Timezone-Offset'); // e.g. -120 for UTC+2

        $geo = self::resolveGeo($request->ip());

        return array_merge($geo, [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $ua,
            'timezone' => $timezone ?: null,
            'gmt_offset_minutes' => $gmtOffset !== null ? (int) $gmtOffset : null,
            'browser' => $browser['name'],
            'browser_version' => $browser['version'],
            'device_type' => $device,
            'platform' => $platform,
            'accept_language' => $request->header('Accept-Language'),
            'referer' => $request->header('Referer'),
        ]);
    }

    private static function parseBrowser(string $ua): array
    {
        if (preg_match('/Edg\/(\d+)/i', $ua, $m)) {
            return ['name' => 'Edge', 'version' => $m[1]];
        }
        if (preg_match('/Chrome\/(\d+)/i', $ua, $m) && ! preg_match('/Chromium/i', $ua)) {
            return ['name' => 'Chrome', 'version' => $m[1]];
        }
        if (preg_match('/Safari\/(\d+)/i', $ua, $m) && ! preg_match('/Chrome/i', $ua)) {
            return ['name' => 'Safari', 'version' => $m[1]];
        }
        if (preg_match('/Firefox\/(\d+)/i', $ua, $m)) {
            return ['name' => 'Firefox', 'version' => $m[1]];
        }
        if (preg_match('/OPR\/(\d+)/i', $ua, $m)) {
            return ['name' => 'Opera', 'version' => $m[1]];
        }

        return ['name' => null, 'version' => null];
    }

    /**
     * Device type: mobile, tablet, or desktop with platform (desktop_windows, desktop_mac, desktop_linux).
     * User-Agent does not distinguish laptop vs stationary PC, so we only differentiate by OS.
     */
    private static function parseDeviceType(string $ua, ?string $platform): ?string
    {
        if (preg_match('/Mobile|Android|iPhone|webOS|BlackBerry|IEMobile/i', $ua)) {
            return preg_match('/Tablet|iPad/i', $ua) ? 'tablet' : 'mobile';
        }
        if ($platform === 'Windows') {
            return 'desktop_windows';
        }
        if ($platform === 'macOS') {
            return 'desktop_mac';
        }
        if ($platform === 'Linux') {
            return 'desktop_linux';
        }

        return 'desktop';
    }

    /** Human-readable label for device_type (for UI). */
    public static function deviceTypeToLabel(?string $deviceType): string
    {
        return match ($deviceType) {
            'desktop_windows' => 'Windows (PC)',
            'desktop_mac' => 'Mac (MacBook / iMac)',
            'desktop_linux' => 'Linux (PC)',
            'desktop' => 'Desktop',
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            default => $deviceType ?: '—',
        };
    }

    private static function parsePlatform(string $ua): ?string
    {
        if (preg_match('/Windows NT/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Mac OS X|Macintosh/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/iPhone|iPad|iOS/i', $ua)) {
            return 'iOS';
        }

        return null;
    }

    private static function resolveGeo(?string $ip): array
    {
        if (! $ip || $ip === '127.0.0.1' || $ip === '::1') {
            return ['country_code' => null, 'region' => null, 'city' => null];
        }

        // Skip private/reserved ranges (e.g. Docker, VPN)
        if (filter_var($ip, FILTER_VALIDATE_IP) && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country_code' => null, 'region' => null, 'city' => null];
        }

        $result = self::resolveGeoViaIpApiCo($ip);
        if ($result !== null) {
            return $result;
        }

        $result = self::resolveGeoViaIpApiCom($ip);
        if ($result !== null) {
            return $result;
        }

        return ['country_code' => null, 'region' => null, 'city' => null];
    }

    /** Try ipapi.co (HTTPS, free tier). Returns null on failure. */
    private static function resolveGeoViaIpApiCo(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)->get("https://ipapi.co/{$ip}/json/");
            if ($response->successful()) {
                $country = $response->json('country_code');
                $region = $response->json('region');
                $city = $response->json('city');
                if ($country !== null) {
                    return [
                        'country_code' => $country,
                        'region' => $region,
                        'city' => $city,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /** Try ip-api.com (HTTP, free). Returns null on failure. */
    private static function resolveGeoViaIpApiCom(string $ip): ?array
    {
        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,countryCode,regionName,city");
            if ($response->successful() && $response->json('status') === 'success') {
                return [
                    'country_code' => $response->json('countryCode'),
                    'region' => $response->json('regionName'),
                    'city' => $response->json('city'),
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }
}
