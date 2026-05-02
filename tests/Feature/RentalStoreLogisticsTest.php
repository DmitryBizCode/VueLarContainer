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

class RentalStoreLogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_end_date_before_minimum_span(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Spanland',
            'iso_code' => 'SP',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Span Owner',
            'email' => 'span-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000003',
        ]);

        $origin = Port::query()->create([
            'name' => 'Span Origin',
            'city' => 'Alpha',
            'country_id' => $countryId,
        ]);
        $destination = Port::query()->create([
            'name' => 'Span Destination',
            'city' => 'Beta',
            'country_id' => $countryId,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 1200.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'SPAN-'.uniqid(),
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

        Vessel::query()->create([
            'name' => 'Span Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 900,
            'status' => 'active',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $start = now()->addDays(12)->toDateString();
        $end = now()->addDays(18)->toDateString();

        $payload = [
            'route_mode' => 'route',
            'route_id' => $route->id,
            'container_id' => $container->id,
            'start_date' => $start,
            'end_date' => $end,
            'cargo_types' => ['electronics'],
            'cargo_details' => null,
            'requested_weight' => null,
            'cargo_volume_cbm' => null,
            'package_count' => null,
            'cargo_value' => null,
            'priority' => 'normal',
            'routing_priority' => null,
            'incoterm' => null,
            'loading_type' => 'fcl',
            'delivery_mode' => 'port_to_port',
            'sustainability_pref' => 'standard',
            'insurance_required' => false,
            'requires_customs_clearance' => false,
            'hazardous_material' => false,
            'requires_escort' => false,
            'seal_required' => false,
            'contact_name' => 'Test User',
            'contact_phone' => '+10005550123',
            'terms_accepted' => true,
        ];

        $this->actingAs($user)->post(route('rentals.request.store'), $payload)
            ->assertSessionHasErrors('end_date');
    }

    public function test_store_accepts_all_delivery_modes(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'DeliveryLand',
            'iso_code' => 'DL',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'DM Owner',
            'email' => 'dm-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000099',
        ]);

        $origin = Port::query()->create([
            'name' => 'DM Origin',
            'city' => 'Alpha',
            'country_id' => $countryId,
        ]);
        $destination = Port::query()->create([
            'name' => 'DM Destination',
            'city' => 'Beta',
            'country_id' => $countryId,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 1200.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'DM-'.uniqid(),
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

        Vessel::query()->create([
            'name' => 'DM Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 900,
            'status' => 'active',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $start = now()->addDays(12)->toDateString();
        $end = now()->addDays(30)->toDateString();

        $basePayload = [
            'route_mode' => 'route',
            'route_id' => $route->id,
            'container_id' => $container->id,
            'start_date' => $start,
            'end_date' => $end,
            'cargo_types' => ['electronics'],
            'cargo_details' => null,
            'requested_weight' => null,
            'cargo_volume_cbm' => null,
            'package_count' => null,
            'cargo_value' => null,
            'priority' => 'normal',
            'routing_priority' => null,
            'incoterm' => null,
            'loading_type' => 'fcl',
            'sustainability_pref' => 'standard',
            'insurance_required' => false,
            'requires_customs_clearance' => false,
            'hazardous_material' => false,
            'requires_escort' => false,
            'seal_required' => false,
            'contact_name' => 'Test User',
            'contact_phone' => '+10005550123',
            'terms_accepted' => true,
        ];

        foreach (['port_to_port', 'door_to_port', 'port_to_door', 'door_to_door'] as $mode) {
            $payload = array_merge($basePayload, ['delivery_mode' => $mode]);

            $this->actingAs($user)
                ->post(route('rentals.request.store'), $payload)
                ->assertSessionDoesntHaveErrors('delivery_mode');
        }
    }
}
