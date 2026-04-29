<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Route as ShippingRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RentalRequestCreateRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_inertia_lists_only_open_routes_with_available_container_at_origin_when_not_show_all(
    ): void {
        config(['logistics.rental_request_show_all_open_routes' => false]);

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'RRLand',
            'iso_code' => 'RR',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'RR Owner',
            'email' => 'rr-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000901',
        ]);

        $o1 = Port::query()->create([
            'name' => 'RR Origin 1',
            'city' => 'A1',
            'country_id' => $countryId,
        ]);
        $d1 = Port::query()->create([
            'name' => 'RR Dest 1',
            'city' => 'B1',
            'country_id' => $countryId,
        ]);
        $o2 = Port::query()->create([
            'name' => 'RR Origin 2',
            'city' => 'A2',
            'country_id' => $countryId,
        ]);
        $d2 = Port::query()->create([
            'name' => 'RR Dest 2',
            'city' => 'B2',
            'country_id' => $countryId,
        ]);

        $rOk = ShippingRoute::query()->create([
            'origin_port_id' => $o1->id,
            'destination_port_id' => $d1->id,
            'estimated_days' => 2,
            'distance' => 200.0,
            'route_status' => 'open',
        ]);
        $rNo = ShippingRoute::query()->create([
            'origin_port_id' => $o2->id,
            'destination_port_id' => $d2->id,
            'estimated_days' => 2,
            'distance' => 200.0,
            'route_status' => 'open',
        ]);

        // Available container at rOk's origin; no container at rNo's origin (o2)
        Container::query()->create([
            'serial_number' => 'RR-LOK-'.str_replace('.', '', uniqid('', true)),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 28200,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => false,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $o1->id,
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $this->actingAs($user)
            ->get(route('rentals.request.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('routes', function ($routes) use ($rOk, $rNo) {
                        $this->assertNotNull($routes);
                        $ids = collect($routes)->pluck('id')->map(fn ($v) => (int) $v)->all();
                        $this->assertCount(1, $ids, 'Only routes with a container at origin are listed');
                        $this->assertContains((int) $rOk->id, $ids, 'The route with container at its origin is listed');
                        $this->assertNotContains((int) $rNo->id, $ids);

                        return true;
                    })
                    ->where('origin_ports', function ($originPorts) use ($o1, $o2) {
                        $this->assertNotNull($originPorts);
                        $ids = collect($originPorts)->pluck('id')->map(fn ($v) => (int) $v)->all();
                        $this->assertContains((int) $o1->id, $ids, 'Origin list includes port with available container');
                        $this->assertNotContains((int) $o2->id, $ids, 'Origin list excludes port with no available container');

                        return true;
                    })
            );
    }

    public function test_create_inertia_lists_all_open_routes_when_show_all_config_is_true(): void
    {
        config(['logistics.rental_request_show_all_open_routes' => true]);

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'RRLandAll',
            'iso_code' => 'RA',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'RR All Owner',
            'email' => 'rra-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000902',
        ]);

        $o1 = Port::query()->create([
            'name' => 'RRA O1',
            'city' => 'A1',
            'country_id' => $countryId,
        ]);
        $d1 = Port::query()->create(['name' => 'RRA D1', 'city' => 'B1', 'country_id' => $countryId]);
        $o2 = Port::query()->create(['name' => 'RRA O2', 'city' => 'A2', 'country_id' => $countryId]);
        $d2 = Port::query()->create(['name' => 'RRA D2', 'city' => 'B2', 'country_id' => $countryId]);

        $r1 = ShippingRoute::query()->create([
            'origin_port_id' => $o1->id,
            'destination_port_id' => $d1->id,
            'estimated_days' => 1,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);
        $r2 = ShippingRoute::query()->create([
            'origin_port_id' => $o2->id,
            'destination_port_id' => $d2->id,
            'estimated_days' => 1,
            'distance' => 150.0,
            'route_status' => 'open',
        ]);
        $this->assertNotSame($r1->id, $r2->id);

        Container::query()->create([
            'serial_number' => 'RRA-'.str_replace('.', '', uniqid('', true)),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 28200,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => false,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $o1->id,
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $this->actingAs($user)
            ->get(route('rentals.request.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('routes', function ($routes) {
                        $this->assertNotNull($routes);
                        $this->assertCount(2, collect($routes), 'With show_all, every open route appears');

                        return true;
                    })
            );
    }
}
