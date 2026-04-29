<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Route as ShippingRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RentalRequestPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_price_without_container_id_when_containers_exist(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Previewland',
            'iso_code' => 'PV',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Preview Owner',
            'email' => 'owner-preview-'.uniqid().'@test.local',
            'phone_number' => '+1000000001',
        ]);

        $origin = Port::query()->create([
            'name' => 'Preview Origin Port',
            'city' => 'Alpha',
            'country_id' => $countryId,
        ]);
        $destination = Port::query()->create([
            'name' => 'Preview Destination Port',
            'city' => 'Beta',
            'country_id' => $countryId,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 2000.0,
            'route_status' => 'open',
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
}
