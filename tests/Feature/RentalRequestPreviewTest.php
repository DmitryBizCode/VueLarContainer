<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Route as ShippingRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalRequestPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_price_without_container_id_when_containers_exist(): void
    {
        $countryId = Country::factory()->create([
            'name' => 'Previewland',
            'iso_code' => 'PV',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Preview Owner',
            'email' => 'owner-preview-'.uniqid().'@test.local',
            'phone_number' => '+1000000001',
        ]);

        $origin = Port::query()->create([
            'name' => 'Preview Origin Port',
            'city' => 'Alpha',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Preview Destination Port',
            'city' => 'Beta',
            'country_id' => $countryId,
            'latitude' => 30.0,
            'longitude' => 40.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 2000.0,
            'route_status' => 'open',
            'sea_path' => [[15.0, 25.0], [25.0, 35.0]],
        ]);

        Container::query()->create([
            'serial_number' => 'PREV-'.uniqid(),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 28200,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $origin->id,
        ]);

        Vessel::query()->create([
            'name' => 'Preview Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 800,
            'status' => 'active',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
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

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data['available_containers']);
        $this->assertGreaterThan(0, (float) ($data['estimated_price'] ?? 0));
        $this->assertIsArray($data['price_breakdown']);
        $this->assertArrayHasKey('route_legs', $data['price_breakdown']);
        $this->assertNotEmpty($data['route_context']['route_legs'] ?? []);
    }

    public function test_preview_blocks_indirect_route_when_transfer_port_has_no_vessel(): void
    {
        $countryId = Country::factory()->create([
            'name' => 'Feasland',
            'iso_code' => 'FS',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Feas Owner',
            'email' => 'owner-feas-'.uniqid().'@test.local',
            'phone_number' => '+1000000002',
        ]);

        $a = Port::query()->create(['name' => 'A Port', 'city' => 'A', 'country_id' => $countryId, 'latitude' => 10.0, 'longitude' => 20.0]);
        $b = Port::query()->create(['name' => 'B Port', 'city' => 'B', 'country_id' => $countryId, 'latitude' => 11.0, 'longitude' => 21.0]);
        $c = Port::query()->create(['name' => 'C Port', 'city' => 'C', 'country_id' => $countryId, 'latitude' => 12.0, 'longitude' => 22.0]);

        // No direct A->C, only A->B->C.
        ShippingRoute::query()->create([
            'origin_port_id' => $a->id,
            'destination_port_id' => $b->id,
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[10.2, 20.2], [10.6, 20.6]],
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $b->id,
            'destination_port_id' => $c->id,
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[11.2, 21.2], [11.6, 21.6]],
        ]);

        Container::query()->create([
            'serial_number' => 'FEAS-'.uniqid(),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 28200,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $a->id,
        ]);

        // Vessel exists only at A (none at B) => indirect plan should be blocked.
        Vessel::query()->create([
            'name' => 'Vessel A',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 800,
            'status' => 'active',
            'current_port_id' => $a->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $start = now()->addDays(10)->toDateString();
        $end = now()->addDays(40)->toDateString();

        $payload = [
            'route_mode' => 'ports',
            'route_id' => null,
            'origin_port_id' => $a->id,
            'destination_port_id' => $c->id,
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
            'routing_priority' => 'speed',
        ];

        $this->actingAs($user)
            ->postJson(route('rentals.request.preview'), $payload)
            ->assertStatus(422);
    }

    public function test_preview_includes_route_plan_and_increases_min_span_when_proxy_waiting_exists(): void
    {
        $countryId = Country::factory()->create([
            'name' => 'Waitland',
            'iso_code' => 'WT',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Wait Owner',
            'email' => 'owner-wait-'.uniqid().'@test.local',
            'phone_number' => '+1000000003',
        ]);

        $a = Port::query()->create(['name' => 'WA Port', 'city' => 'A', 'country_id' => $countryId, 'latitude' => 10.0, 'longitude' => 20.0]);
        $b = Port::query()->create(['name' => 'WB Port', 'city' => 'B', 'country_id' => $countryId, 'latitude' => 11.0, 'longitude' => 21.0]);
        $c = Port::query()->create(['name' => 'WC Port', 'city' => 'C', 'country_id' => $countryId, 'latitude' => 12.0, 'longitude' => 22.0]);

        ShippingRoute::query()->create([
            'origin_port_id' => $a->id,
            'destination_port_id' => $b->id,
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[10.2, 20.2], [10.6, 20.6]],
        ]);
        ShippingRoute::query()->create([
            'origin_port_id' => $b->id,
            'destination_port_id' => $c->id,
            'estimated_days' => 2,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[11.2, 21.2], [11.6, 21.6]],
        ]);

        Container::query()->create([
            'serial_number' => 'WAIT-'.uniqid(),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 28200,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $a->id,
        ]);

        $start = now()->addDays(10)->startOfDay();

        Vessel::query()->create([
            'name' => 'Vessel WA',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 800,
            'status' => 'active',
            'current_port_id' => $a->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);
        Vessel::query()->create([
            'name' => 'Vessel WB',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 800,
            'status' => 'active',
            'current_port_id' => $b->id,
            'berth_busy_until' => $start->copy()->addDays(3),
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $payload = [
            'route_mode' => 'ports',
            'route_id' => null,
            'origin_port_id' => $a->id,
            'destination_port_id' => $c->id,
            'container_id' => null,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(40)->toDateString(),
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
            'routing_priority' => 'speed',
        ];

        $response = $this->actingAs($user)->postJson(route('rentals.request.preview'), $payload);
        $response->assertOk();
        $data = $response->json();

        $this->assertIsArray($data['route_plan'] ?? null);
        $this->assertNotEmpty($data['route_plan']['segments'] ?? []);
        $this->assertGreaterThan(0, (int) ($data['route_plan']['total_waiting_time_hours'] ?? 0));
        $this->assertGreaterThan(0, (int) ($data['route_context']['min_rental_span_days'] ?? 0));
    }
}
