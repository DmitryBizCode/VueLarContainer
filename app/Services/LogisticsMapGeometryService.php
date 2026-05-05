<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class LogisticsMapGeometryService
{
    private const EARTH_RADIUS_KM = 6371.0;

    private const GC_SEGMENT_CAP = 48;

    private const GC_SEGMENT_FLOOR = 8;

    /**
     * IMPORTANT: for maritime logistics we only draw routes that have explicit sea-path geometry stored.
     * This avoids misleading polylines that can cross land when using great-circle fallbacks.
     */
    private const REQUIRE_STORED_GEOMETRY_FOR_RENDER = true;

    /**
     * @param  list<array{0: float, 1: float}|array{lat: float, lng: float}>|array{lat: float, lng: float}|null  $seaPath
     * @return list<array{0: float, 1: float}>
     */
    public static function resolvePath(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        ?array $seaPath
    ): array {
        $merged = self::mergeSeaPathWithEndpoints($originLat, $originLng, $destLat, $destLng, $seaPath);
        if (count($merged) >= 3) {
            return self::densifyPathAlongGreatCircles($merged);
        }

        $segments = self::greatCircleSegmentCount($originLat, $originLng, $destLat, $destLng);

        return self::greatCirclePolyline($originLat, $originLng, $destLat, $destLng, $segments);
    }

    /**
     * Returns a resolved polyline only when it is safe to draw:
     * - has stored sea_path geometry (≥1 waypoint), OR
     * - the great-circle hop is short enough that cutting a tiny corner is acceptable.
     *
     * Longer legs with no geometry return null so callers can skip rendering instead of drawing fake land-crossing lines.
     *
     * @param  list<array{0: float, 1: float}|array{lat: float, lng: float}>|array{lat: float, lng: float}|null  $seaPath
     * @return list<array{0: float, 1: float}>|null
     */
    public static function resolvePathIfNavigable(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        ?array $seaPath
    ): ?array {
        $merged = self::mergeSeaPathWithEndpoints($originLat, $originLng, $destLat, $destLng, $seaPath);
        if (count($merged) >= 3) {
            return self::densifyPathAlongGreatCircles($merged);
        }

        if (! self::REQUIRE_STORED_GEOMETRY_FOR_RENDER) {
            $segments = self::greatCircleSegmentCount($originLat, $originLng, $destLat, $destLng);

            return self::greatCirclePolyline($originLat, $originLng, $destLat, $destLng, $segments);
        }

        return null;
    }

    /**
     * @param  list<array{0: float, 1: float}|array{lat: float, lng: float}>|array{lat: float, lng: float}|null  $seaPath
     */
    public static function hasStoredGeometry(?array $seaPath): bool
    {
        if (! is_array($seaPath) || $seaPath === []) {
            return false;
        }
        foreach ($seaPath as $row) {
            if (is_array($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $path
     * @return array{lat: float, lng: float}
     */
    public static function interpolateAlongPath(array $path, float $t): array
    {
        $t = min(1.0, max(0.0, $t));
        if ($path === []) {
            return ['lat' => 0.0, 'lng' => 0.0];
        }
        if (count($path) === 1) {
            return ['lat' => $path[0][0], 'lng' => $path[0][1]];
        }

        $lengths = [];
        $total = 0.0;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $a = $path[$i];
            $b = $path[$i + 1];
            $len = self::haversineKm($a[0], $a[1], $b[0], $b[1]);
            $lengths[] = $len;
            $total += $len;
        }

        if ($total <= 1e-9) {
            return ['lat' => $path[0][0], 'lng' => $path[0][1]];
        }

        $target = $t * $total;
        $acc = 0.0;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $len = $lengths[$i];
            if ($len <= 1e-9) {
                continue;
            }
            if ($acc + $len >= $target) {
                $u = ($target - $acc) / $len;
                $a = $path[$i];
                $b = $path[$i + 1];

                return self::greatCirclePoint($a[0], $a[1], $b[0], $b[1], $u);
            }
            $acc += $len;
        }

        $last = $path[count($path) - 1];

        return ['lat' => $last[0], 'lng' => $last[1]];
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public static function interpolateLatLng(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        float $t
    ): array {
        $path = self::greatCirclePolyline($originLat, $originLng, $destLat, $destLng, self::greatCircleSegmentCount($originLat, $originLng, $destLat, $destLng));

        return self::interpolateAlongPath($path, $t);
    }

    /**
     * Progress along a sea leg from depart to arrive (0..1), or null if interval invalid.
     */
    public static function seaLegProgress(
        ?CarbonInterface $depart,
        ?CarbonInterface $arrive,
        CarbonImmutable $now
    ): ?float {
        if ($depart === null || $arrive === null) {
            return null;
        }

        if ($arrive->lte($depart)) {
            return null;
        }

        if ($now->lte($depart)) {
            return 0.0;
        }

        if ($now->gte($arrive)) {
            return 1.0;
        }

        $total = max(1, $depart->diffInSeconds($arrive));
        $elapsed = $depart->diffInSeconds($now);

        return min(1.0, max(0.0, $elapsed / $total));
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private static function mergeSeaPathWithEndpoints(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        ?array $seaPath
    ): array {
        if ($seaPath === null || $seaPath === []) {
            return [[$originLat, $originLng], [$destLat, $destLng]];
        }

        $points = [];
        foreach ($seaPath as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['lat'], $row['lng'])) {
                $points[] = [(float) $row['lat'], (float) $row['lng']];
            } elseif (array_is_list($row) && count($row) >= 2) {
                $points[] = [(float) $row[0], (float) $row[1]];
            }
        }

        $out = [[$originLat, $originLng]];
        foreach ($points as $p) {
            if (self::coordsApproxEqual($p, $out[count($out) - 1])) {
                continue;
            }
            if (self::coordsApproxEqual($p, [$destLat, $destLng])) {
                continue;
            }
            $out[] = $p;
        }
        if (! self::coordsApproxEqual($out[count($out) - 1], [$destLat, $destLng])) {
            $out[] = [$destLat, $destLng];
        }

        return $out;
    }

    /**
     * Subdivide each leg so the map client (Leaflet: straight segments in Web Mercator) approximates
     * great-circle arcs between stored waypoints, reducing false “land cuts” on long chords.
     *
     * @param  list<array{0: float, 1: float}>  $path
     * @return list<array{0: float, 1: float}>
     */
    private static function densifyPathAlongGreatCircles(array $path): array
    {
        if (count($path) < 2) {
            return $path;
        }

        $out = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $a = $path[$i];
            $b = $path[$i + 1];
            $segments = self::greatCircleSegmentCount($a[0], $a[1], $b[0], $b[1]);
            $chunk = self::greatCirclePolyline($a[0], $a[1], $b[0], $b[1], $segments);
            if ($out !== []) {
                array_shift($chunk);
            }
            foreach ($chunk as $p) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private static function greatCirclePolyline(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        int $segments
    ): array {
        $segments = max(1, $segments);
        $out = [];
        for ($i = 0; $i <= $segments; $i++) {
            $f = $i / $segments;
            $p = self::greatCirclePoint($lat1, $lng1, $lat2, $lng2, $f);
            $pair = [$p['lat'], $p['lng']];
            if ($out !== [] && self::coordsApproxEqual($pair, $out[count($out) - 1])) {
                continue;
            }
            $out[] = $pair;
        }

        return $out;
    }

    /**
     * @return array{lat: float, lng: float}
     */
    private static function greatCirclePoint(float $lat1, float $lng1, float $lat2, float $lng2, float $t): array
    {
        $t = min(1.0, max(0.0, $t));
        $φ1 = deg2rad($lat1);
        $λ1 = deg2rad($lng1);
        $φ2 = deg2rad($lat2);
        $λ2 = deg2rad($lng2);

        $x1 = cos($φ1) * cos($λ1);
        $y1 = cos($φ1) * sin($λ1);
        $z1 = sin($φ1);
        $x2 = cos($φ2) * cos($λ2);
        $y2 = cos($φ2) * sin($λ2);
        $z2 = sin($φ2);

        $ω = acos(max(-1.0, min(1.0, $x1 * $x2 + $y1 * $y2 + $z1 * $z2)));
        if ($ω < 1e-10) {
            return ['lat' => $lat1 + ($lat2 - $lat1) * $t, 'lng' => $lng1 + ($lng2 - $lng1) * $t];
        }

        $sinω = sin($ω);
        $a = sin((1 - $t) * $ω) / $sinω;
        $b = sin($t * $ω) / $sinω;
        $x = $a * $x1 + $b * $x2;
        $y = $a * $y1 + $b * $y2;
        $z = $a * $z1 + $b * $z2;

        $φ = atan2($z, sqrt($x * $x + $y * $y));
        $λ = atan2($y, $x);

        return ['lat' => rad2deg($φ), 'lng' => rad2deg($λ)];
    }

    /**
     * Compass bearing (0–359°, clockwise from North) at progress t along the path.
     *
     * @param  list<array{0: float, 1: float}>  $path
     */
    public static function bearingAlongPath(array $path, float $t): float
    {
        $t = min(1.0, max(0.0, $t));
        $n = count($path);
        if ($n < 2) {
            return 0.0;
        }

        $lengths = [];
        $total = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $len = self::haversineKm($path[$i][0], $path[$i][1], $path[$i + 1][0], $path[$i + 1][1]);
            $lengths[] = $len;
            $total += $len;
        }

        if ($total <= 1e-9) {
            return self::initialBearing($path[0][0], $path[0][1], $path[$n - 1][0], $path[$n - 1][1]);
        }

        $target = $t * $total;
        $acc = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $len = $lengths[$i];
            if ($len <= 1e-9) {
                continue;
            }
            if ($acc + $len >= $target) {
                return self::initialBearing($path[$i][0], $path[$i][1], $path[$i + 1][0], $path[$i + 1][1]);
            }
            $acc += $len;
        }

        return self::initialBearing($path[$n - 2][0], $path[$n - 2][1], $path[$n - 1][0], $path[$n - 1][1]);
    }

    private static function greatCircleSegmentCount(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $km = self::haversineKm($lat1, $lng1, $lat2, $lng2);
        $byDistance = (int) round($km / 250.0);

        return max(self::GC_SEGMENT_FLOOR, min(self::GC_SEGMENT_CAP, $byDistance));
    }

    private static function initialBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δλ = deg2rad($lng2 - $lng1);

        $y = sin($Δλ) * cos($φ2);
        $x = cos($φ1) * sin($φ2) - sin($φ1) * cos($φ2) * cos($Δλ);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function coordsApproxEqual(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) < 1e-7 && abs($a[1] - $b[1]) < 1e-7;
    }
}
