<?php

namespace Tests\Unit;

use App\Models\Route as ShippingRoute;
use App\Services\RoutePathfinderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutePathfinderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_multi_hop_for_time_when_direct_is_slower(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Pathland',
            'iso_code' => 'PH',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ports = [];
        foreach (['A', 'B', 'C'] as $label) {
            $ports[$label] = DB::table('ports')->insertGetId([
                'country_id' => $countryId,
                'name' => "Port {$label}",
                'city' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ShippingRoute::query()->create([
            'origin_port_id' => $ports['A'],
            'destination_port_id' => $ports['C'],
            'estimated_days' => 50,
            'distance' => 5.0,
            'route_status' => 'open',
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $ports['A'],
            'destination_port_id' => $ports['B'],
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $ports['B'],
            'destination_port_id' => $ports['C'],
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
        ]);

        $svc = new RoutePathfinderService;
        $path = $svc->findPath($ports['A'], $ports['C'], 'time');

        $this->assertNotNull($path);
        $this->assertTrue($path['multi_hop']);
        $this->assertSame(4, $path['total_days']);
        $this->assertCount(2, $path['legs']);
    }

    public function test_prefers_direct_for_cost_when_cheaper_than_two_hops(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Costland',
            'iso_code' => 'CS',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ports = [];
        foreach (['X', 'Y', 'Z'] as $label) {
            $ports[$label] = DB::table('ports')->insertGetId([
                'country_id' => $countryId,
                'name' => "Harbor {$label}",
                'city' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ShippingRoute::query()->create([
            'origin_port_id' => $ports['X'],
            'destination_port_id' => $ports['Z'],
            'estimated_days' => 40,
            'distance' => 10.0,
            'route_status' => 'open',
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $ports['X'],
            'destination_port_id' => $ports['Y'],
            'estimated_days' => 1,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $ports['Y'],
            'destination_port_id' => $ports['Z'],
            'estimated_days' => 1,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $svc = new RoutePathfinderService;
        $path = $svc->findPath($ports['X'], $ports['Z'], 'cost');

        $this->assertNotNull($path);
        $this->assertFalse($path['multi_hop']);
        $this->assertSame(10.0, $path['total_distance']);
        $this->assertCount(1, $path['legs']);
    }
}
