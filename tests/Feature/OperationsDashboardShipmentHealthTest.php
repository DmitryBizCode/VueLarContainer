<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsDashboardShipmentHealthTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'ShipLand',
            'iso_code' => 'SL',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000000',
        ]);

        $origin = Port::query()->create([
            'name' => 'Origin',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Dest',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 30.0,
            'longitude' => 40.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 5,
            'distance' => 500.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'C-'.uniqid(),
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => false,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $origin->id,
        ]);

        $vessel = Vessel::query()->create([
            'name' => 'Ship Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 500,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        return compact('countryId', 'origin', 'destination', 'route', 'container', 'vessel');
    }

    public function test_shipment_health_and_incidents_counts_match_user_shipments(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => $base['route']->id,
            'origin_port_id' => $base['origin']->id,
            'destination_port_id' => $base['destination']->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(7),
            'rental_days' => 12,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
        ]);

        $shipmentDelayed = Shipment::query()->create([
            'vessel_id' => $base['vessel']->id,
            'route_id' => $base['route']->id,
            'leg_sequence' => 1,
            'departure_date' => now()->subDays(4),
            'arrival_date' => now()->subDay(),
            'actual_departure_date' => now()->subDays(4),
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipmentDelayed->id,
            'container_id' => $base['container']->id,
            'rental_id' => $rental->id,
            'loaded_at' => now()->subDays(4),
            'condition_on_arrival' => 'good',
        ]);

        DB::table('incidents')->insert([
            'type' => 'damage',
            'severity' => 'high',
            'description' => 'Test',
            'shipment_id' => $shipmentDelayed->id,
            'container_id' => null,
            'insurance_policy_number' => null,
            'reported_at' => now(),
            'resolved_at' => null,
            'resolution_status' => 'under_investigation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('shipmentOverview.inTransitCount', 1)
                ->where('shipmentOverview.delayedCount', 1)
                ->where('incidentOverview.openCount', 1)
                ->where('incidentOverview.highSeverityOpenCount', 1)
            );
    }
}
