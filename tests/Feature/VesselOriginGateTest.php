<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Route as ShippingRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VesselOriginGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_no_containers_when_no_vessel_at_origin(): void
    {
        config(['logistics.require_vessel_at_origin' => true]);

        $countryId = Country::factory()->create([
            'name' => 'Gateland',
            'iso_code' => 'GT',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Gate Owner',
            'email' => 'gate-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000002',
        ]);

        $origin = Port::query()->create([
            'name' => 'Gate Origin',
            'city' => 'Alpha',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Gate Destination',
            'city' => 'Beta',
            'country_id' => $countryId,
            'latitude' => 30.0,
            'longitude' => 40.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 3,
            'distance' => 900.0,
            'route_status' => 'open',
            'sea_path' => [[15.0, 25.0], [25.0, 35.0]],
        ]);

        Container::query()->create([
            'serial_number' => 'GATE-'.uniqid(),
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
            'current_port_id' => $origin->id,
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $start = now()->addDays(10)->toDateString();
        $end = now()->addDays(30)->toDateString();

        $payload = [
            'route_mode' => 'route',
            'route_id' => $route->id,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'container_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'cargo_types' => ['electronics'],
            'requested_weight' => null,
            'cargo_volume_cbm' => null,
            'package_count' => null,
            'cargo_value' => null,
            'priority' => 'normal',
            'delivery_mode' => 'port_to_port',
            'loading_type' => 'fcl',
            'sustainability_pref' => 'standard',
            'insurance_required' => false,
            'requires_customs_clearance' => false,
            'hazardous_material' => false,
            'requires_escort' => false,
            'seal_required' => false,
        ];

        $response = $this->actingAs($user)->postJson(route('rentals.request.preview'), $payload);

        $response->assertStatus(422);
    }
}
