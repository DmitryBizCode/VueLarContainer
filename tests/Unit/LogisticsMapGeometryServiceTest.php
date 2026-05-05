<?php

namespace Tests\Unit;

use App\Services\LogisticsMapGeometryService;
use PHPUnit\Framework\TestCase;

class LogisticsMapGeometryServiceTest extends TestCase
{
    public function test_resolve_path_without_waypoints_is_great_circle_polyline(): void
    {
        $path = LogisticsMapGeometryService::resolvePath(0.0, 0.0, 50.0, 20.0, null);
        $this->assertGreaterThanOrEqual(8, count($path));
        $this->assertEqualsWithDelta(0.0, $path[0][0], 1e-6);
        $this->assertEqualsWithDelta(0.0, $path[0][1], 1e-6);
        $last = $path[count($path) - 1];
        $this->assertEqualsWithDelta(50.0, $last[0], 1e-6);
        $this->assertEqualsWithDelta(20.0, $last[1], 1e-6);
    }

    public function test_interpolate_along_path_matches_interpolate_lat_lng_for_open_ocean(): void
    {
        $path = LogisticsMapGeometryService::resolvePath(0.0, 0.0, 40.0, -30.0, null);
        $mid = LogisticsMapGeometryService::interpolateAlongPath($path, 0.5);
        $ref = LogisticsMapGeometryService::interpolateLatLng(0.0, 0.0, 40.0, -30.0, 0.5);
        $this->assertEqualsWithDelta($ref['lat'], $mid['lat'], 0.0001);
        $this->assertEqualsWithDelta($ref['lng'], $mid['lng'], 0.0001);
    }

    public function test_stored_waypoints_merge_into_polyline(): void
    {
        $path = LogisticsMapGeometryService::resolvePath(0.0, 0.0, 10.0, 10.0, [[4.0, 2.0], [8.0, 6.0]]);
        $this->assertGreaterThan(4, count($path));
        $this->assertEqualsWithDelta(0.0, $path[0][0], 1e-6);
        $this->assertEqualsWithDelta(0.0, $path[0][1], 1e-6);
        $this->assertTrue($this->pathContainsNear($path, 4.0, 2.0));
        $this->assertTrue($this->pathContainsNear($path, 8.0, 6.0));
        $last = $path[count($path) - 1];
        $this->assertEqualsWithDelta(10.0, $last[0], 1e-6);
        $this->assertEqualsWithDelta(10.0, $last[1], 1e-6);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $path
     */
    private function pathContainsNear(array $path, float $lat, float $lng, float $eps = 1e-4): bool
    {
        foreach ($path as $p) {
            if (abs($p[0] - $lat) < $eps && abs($p[1] - $lng) < $eps) {
                return true;
            }
        }

        return false;
    }

    public function test_haversine_km_is_positive_for_distinct_points(): void
    {
        $km = LogisticsMapGeometryService::haversineKm(51.9244, 4.4777, 53.5511, 9.9937);
        $this->assertGreaterThan(350.0, $km);
        $this->assertLessThan(550.0, $km);
    }
}
