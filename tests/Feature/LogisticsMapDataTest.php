<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Vessel;
use App\Services\LogisticsMapGeometryService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PortSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogisticsMapDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_map_data(): void
    {
        $this->getJson(route('rentals.map-data'))->assertUnauthorized();
    }

    public function test_authenticated_user_receives_ports_payload_shape(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'ports',
            'route_edges',
            'vessel_positions',
            'positions',
        ]);
    }

    public function test_route_edges_and_vessel_positions_for_open_route_and_active_shipment(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Mapland',
            'iso_code' => 'ML',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Map Owner',
            'email' => 'map-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000009',
        ]);

        $origin = Port::query()->create([
            'name' => 'Map Origin Port',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Map Dest Port',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 30.0,
            'longitude' => 40.0,
        ]);

        // sea_path required: routes without stored geometry are no longer drawn on the map —
        // the controller skips them instead of falling back to a land-crossing great-circle line.
        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 5,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[15.0, 25.0], [25.0, 35.0]],
        ]);

        $container = Container::query()->create([
            'serial_number' => 'MAP-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Map Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 500,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $depart = now()->subDays(2);
        $arrive = now()->addDays(2);
        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => $depart,
            'arrival_date' => $arrive,
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);
        $otherUser = User::factory()->create(['country_id' => $countryId]);

        $rentalOther = Rental::query()->create([
            'user_id' => $otherUser->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 'in_progress',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 100.00,
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rentalOther->id,
            'loaded_at' => now(),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $edges = $response->json('route_edges');
        $this->assertNotEmpty($edges);
        $this->assertEquals($route->id, $edges[0]['id']);
        $expectedPath = LogisticsMapGeometryService::resolvePath(10.0, 20.0, 30.0, 40.0, [[15.0, 25.0], [25.0, 35.0]]);
        $this->assertEquals(
            array_map(static fn (array $p) => [(float) $p[0], (float) $p[1]], $expectedPath),
            array_map(static fn ($p) => [(float) $p[0], (float) $p[1]], $edges[0]['path'])
        );

        // Non-ops users only see vessels linked to their own rentals; $user has no rentals so fleet is empty.
        $fleet = $response->json('vessel_positions');
        $this->assertEmpty($fleet);
    }

    public function test_route_edges_use_stored_sea_path_when_present(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandSea',
            'iso_code' => 'MS',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $origin = Port::query()->create([
            'name' => 'Sea Path A',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Sea Path B',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 500.0,
            'route_status' => 'open',
            'sea_path' => [[3.0, 3.0], [7.0, 7.0]],
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $edge = collect($response->json('route_edges'))->firstWhere('id', $route->id);
        $this->assertNotNull($edge);
        $expected = LogisticsMapGeometryService::resolvePath(0.0, 0.0, 10.0, 10.0, [[3.0, 3.0], [7.0, 7.0]]);
        $this->assertEquals(
            array_map(static fn (array $p) => [(float) $p[0], (float) $p[1]], $expected),
            array_map(static fn ($p) => [(float) $p[0], (float) $p[1]], $edge['path'])
        );
        $this->assertGreaterThanOrEqual(3, count($edge['path']));
    }

    public function test_vessel_position_marked_user_shipment_when_rental_belongs_to_actor(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Mapland2',
            'iso_code' => 'M2',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Map Owner 2',
            'email' => 'map-owner2-'.uniqid().'@test.local',
            'phone_number' => '+1000000010',
        ]);

        $origin = Port::query()->create([
            'name' => 'Map Origin 2',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Map Dest 2',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 3,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'MAP2-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Map Vessel 2',
            'imo_number' => 'IM2'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 400,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $rentalMine = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 'in_progress',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 200.00,
        ]);

        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => now()->subDay(),
            'arrival_date' => now()->addDay(),
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-'.strtoupper(uniqid()),
            'status' => 'in_progress',
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rentalMine->id,
            'loaded_at' => now(),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $fleet = $response->json('vessel_positions');
        $this->assertCount(1, $fleet);
        $this->assertTrue($fleet[0]['is_user_shipment']);
        $this->assertTrue($fleet[0]['has_rental_cargo']);
        $this->assertSame(1, $fleet[0]['rental_cargo_count']);
    }

    public function test_vessel_position_not_marked_user_shipment_for_completed_or_expired_user_rental(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandUserOff',
            'iso_code' => 'MUO',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000011',
        ]);

        $origin = Port::query()->create([
            'name' => 'Origin',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Dest',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 500.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'C-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 500,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => now()->subDays(2),
            'arrival_date' => now()->addDays(2),
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 100.00,
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'loaded_at' => now()->subDays(2),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        // Non-ops users only see vessels linked to their own active-window rentals.
        // The user's rental has a past end_date so it is excluded from the map window — no vessels returned.
        $fleet = $response->json('vessel_positions');
        $this->assertEmpty($fleet);
    }

    public function test_container_marker_snaps_to_destination_after_port_operations_complete(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Snapland',
            'iso_code' => 'SN',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Snap Owner',
            'email' => 'snap-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000099',
        ]);

        $origin = Port::query()->create([
            'name' => 'Snap Origin Port',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 11.0,
            'longitude' => 22.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Snap Dest Port',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 33.0,
            'longitude' => 44.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 5,
            'distance' => 500.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'SNAP-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Snap Vessel',
            'imo_number' => 'IMO'.substr(str_replace('.', '', uniqid('', true)), 0, 12),
            'capacity_teu' => 500,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => now()->subDays(5),
            'arrival_date' => now()->subDays(2),
            'actual_departure_date' => now()->subDays(5),
            'actual_arrival_date' => now()->subDays(2),
            'port_operations_until' => now()->subDay(),
            'tracking_number' => 'TRK-'.strtoupper(uniqid()),
            'status' => 'completed',
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeek(),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 100.00,
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'loaded_at' => now()->subDays(5),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $pos = collect($response->json('positions'))->firstWhere('rental_id', $rental->id);
        $this->assertNotNull($pos);
        $this->assertEqualsWithDelta(33.0, (float) $pos['latitude'], 0.0001);
        $this->assertEqualsWithDelta(44.0, (float) $pos['longitude'], 0.0001);
    }

    public function test_map_includes_imminent_start_excludes_far_future_and_expired_positions(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandFilter',
            'iso_code' => 'MF',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Filter Owner',
            'email' => 'filter-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000088',
        ]);

        $origin = Port::query()->create([
            'name' => 'Filter Origin',
            'city' => 'FO',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Filter Dest',
            'city' => 'FD',
            'country_id' => $countryId,
            'latitude' => 20.0,
            'longitude' => 20.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $mkContainer = static function () use ($owner, $origin): Container {
            return Container::query()->create([
                'serial_number' => 'MAPF-'.uniqid(),
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
        };

        $user = User::factory()->create(['country_id' => $countryId]);

        $imminentStart = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $mkContainer()->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(7),
            'status' => 'in_progress',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 10.00,
        ]);

        $farFuture = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $mkContainer()->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->addDays(120),
            'end_date' => now()->addDays(200),
            'status' => 'scheduled',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 10.00,
        ]);

        $expired = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $mkContainer()->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 11.00,
        ]);

        $active = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $mkContainer()->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(2),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 12.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $ids = collect($response->json('positions'))->pluck('rental_id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($imminentStart->id, $ids);
        $this->assertNotContains($farFuture->id, $ids);
        $this->assertNotContains($expired->id, $ids);

        $imminentPos = collect($response->json('positions'))->firstWhere('rental_id', $imminentStart->id);
        $this->assertNotNull($imminentPos);
        $this->assertEqualsWithDelta(10.0, (float) $imminentPos['latitude'], 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $imminentPos['longitude'], 0.0001);
    }

    public function test_map_includes_approved_or_scheduled_rentals_when_start_date_has_begun(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandStatusLag',
            'iso_code' => 'MSL',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000087',
        ]);

        $origin = Port::query()->create([
            'name' => 'Lag Origin',
            'city' => 'LO',
            'country_id' => $countryId,
            'latitude' => 1.0,
            'longitude' => 1.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Lag Dest',
            'city' => 'LD',
            'country_id' => $countryId,
            'latitude' => 2.0,
            'longitude' => 2.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'LAG-'.uniqid(),
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

        $scheduledButStarted = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(2),
            'status' => 'scheduled',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 12.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $ids = collect($response->json('positions'))->pluck('rental_id')->all();
        $this->assertContains($scheduledButStarted->id, $ids);
    }

    public function test_map_includes_user_active_rental_even_when_ports_outside_whitelist(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandWhitelistBypass',
            'iso_code' => 'MWB',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000086',
        ]);

        // Names intentionally not present in config('logistics_map.port_names')
        $origin = Port::query()->create([
            'name' => 'Port Outside Whitelist A',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 5.0,
            'longitude' => 5.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Port Outside Whitelist B',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 6.0,
            'longitude' => 6.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'WL-'.uniqid(),
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

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(2),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 12.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $ids = collect($response->json('positions'))->pluck('rental_id')->all();
        $this->assertContains($rental->id, $ids);
    }

    public function test_map_falls_back_to_time_interpolation_when_snapshot_missing(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandFallback',
            'iso_code' => 'MFB',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000085',
        ]);

        $origin = Port::query()->create([
            'name' => 'Fallback Origin',
            'city' => 'FO',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Fallback Dest',
            'city' => 'FD',
            'country_id' => $countryId,
            'latitude' => 20.0,
            'longitude' => 20.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
            'sea_path' => [[12.0, 12.0], [18.0, 18.0]],
        ]);

        $container = Container::query()->create([
            'serial_number' => 'FB-'.uniqid(),
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
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 12.00,
        ]);

        // Intentionally do not create ContainerSimulationSnapshot for this container.

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $pos = collect($response->json('positions'))->firstWhere('rental_id', $rental->id);
        $this->assertNotNull($pos);
        $this->assertNotNull($pos['latitude']);
        $this->assertNotNull($pos['longitude']);
        $expectedPath = LogisticsMapGeometryService::resolvePath(10.0, 10.0, 20.0, 20.0, [[12.0, 12.0], [18.0, 18.0]]);
        $midRental = LogisticsMapGeometryService::interpolateAlongPath($expectedPath, 0.5);
        $this->assertEqualsWithDelta($midRental['lat'], (float) $pos['latitude'], 0.02);
        $this->assertEqualsWithDelta($midRental['lng'], (float) $pos['longitude'], 0.02);
    }

    public function test_container_position_matches_vessel_on_shipment_sea_leg_without_snapshot(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandSeaSync',
            'iso_code' => 'MSS',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Sync Owner',
            'email' => 'sync-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000101',
        ]);

        $origin = Port::query()->create([
            'name' => 'Sync Origin',
            'city' => 'SO',
            'country_id' => $countryId,
            'latitude' => 1.0,
            'longitude' => 2.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Sync Dest',
            'city' => 'SD',
            'country_id' => $countryId,
            'latitude' => 5.0,
            'longitude' => 6.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'SYNC-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Sync Vessel',
            'imo_number' => 'SYN'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 200,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeek(),
            'status' => 'in_progress',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 50.00,
        ]);

        $dep = now()->subHours(6);
        $arr = now()->addHours(6);
        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => $dep,
            'arrival_date' => $arr,
            'actual_departure_date' => $dep,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-SYNC-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'loaded_at' => $dep,
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();

        $pos = collect($response->json('positions'))->firstWhere('rental_id', $rental->id);
        $this->assertNotNull($pos);
        $fleet = collect($response->json('vessel_positions'))->firstWhere('is_user_shipment', true);
        $this->assertNotNull($fleet);
        $this->assertEqualsWithDelta((float) $fleet['latitude'], (float) $pos['latitude'], 0.0001);
        $this->assertEqualsWithDelta((float) $fleet['longitude'], (float) $pos['longitude'], 0.0001);
    }

    public function test_idle_vessel_is_not_returned_on_user_only_map_endpoint_even_for_admin_role(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandIdle',
            'iso_code' => 'MI',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $port = Port::query()->create([
            'name' => 'Idle Port',
            'city' => 'Idle City',
            'country_id' => $countryId,
            'latitude' => 55.0,
            'longitude' => 12.0,
        ]);

        Vessel::query()->create([
            'name' => 'Idle Vessel',
            'imo_number' => 'IDL'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 200,
            'status' => 'in_port',
            'current_port_id' => $port->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        // Regular users do NOT see idle vessels — only vessels tied to their own active shipments.
        $regularUser = User::factory()->create(['country_id' => $countryId]);
        $regularResponse = $this->actingAs($regularUser)->getJson(route('rentals.map-data'));
        $regularResponse->assertOk();
        $this->assertEmpty($regularResponse->json('vessel_positions'));

        // Even admin role is user-only on non-admin routes.
        $opsUser = User::factory()->create(['country_id' => $countryId, 'role' => 'admin']);
        $opsResponse = $this->actingAs($opsUser)->getJson(route('rentals.map-data'));
        $opsResponse->assertOk();
        $this->assertEmpty($opsResponse->json('vessel_positions'));
    }

    public function test_scheduled_shipment_is_included_at_route_origin(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandSched',
            'iso_code' => 'MSD',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Sched Owner',
            'email' => 'sched-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000020',
        ]);

        $origin = Port::query()->create([
            'name' => 'Sched Origin',
            'city' => 'SO',
            'country_id' => $countryId,
            'latitude' => 1.0,
            'longitude' => 2.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Sched Dest',
            'city' => 'SD',
            'country_id' => $countryId,
            'latitude' => 5.0,
            'longitude' => 6.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 400.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'SCH-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Sched Vessel',
            'imo_number' => 'SCH'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 300,
            'status' => 'scheduled',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $depart = now()->addDay();
        $arrive = now()->addDays(5);
        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => $depart,
            'arrival_date' => $arrive,
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-S-'.strtoupper(uniqid()),
            'status' => 'scheduled',
        ]);

        // The requesting user must own the rental for the vessel to appear.
        $user = User::factory()->create(['country_id' => $countryId]);
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
            'status' => 'scheduled',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 50.00,
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'loaded_at' => now(),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $fleet = $response->json('vessel_positions');
        $this->assertCount(1, $fleet);
        $this->assertSame($shipment->id, $fleet[0]['shipment_id']);
        $this->assertSame('scheduled', $fleet[0]['shipment_status']);
        $this->assertTrue($fleet[0]['has_rental_cargo']);
        $expectedPath = LogisticsMapGeometryService::resolvePath(1.0, 2.0, 5.0, 6.0, null);
        $originPoint = LogisticsMapGeometryService::interpolateAlongPath($expectedPath, 0.0);
        $this->assertEqualsWithDelta($originPoint['lat'], $fleet[0]['latitude'], 0.0001);
        $this->assertEqualsWithDelta($originPoint['lng'], $fleet[0]['longitude'], 0.0001);
    }

    public function test_same_vessel_dedup_prefers_in_transit_over_scheduled(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandDedup',
            'iso_code' => 'MD',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Dedup Owner',
            'email' => 'dedup-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000040',
        ]);

        $origin = Port::query()->create([
            'name' => 'Dedup A',
            'city' => 'A',
            'country_id' => $countryId,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Dedup B',
            'city' => 'B',
            'country_id' => $countryId,
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 3,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $vessel = Vessel::query()->create([
            'name' => 'Dedup Vessel',
            'imo_number' => 'DDP'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 100,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $mkContainer = static function () use ($owner, $origin): Container {
            return Container::query()->create([
                'serial_number' => 'DD-'.str_replace('.', '', uniqid('', true)),
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
        };

        $user = User::factory()->create(['country_id' => $countryId]);

        $scheduledShip = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => now()->addWeek(),
            'arrival_date' => now()->addWeeks(2),
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-D1-'.strtoupper(uniqid()),
            'status' => 'scheduled',
            'updated_at' => now()->subHour(),
        ]);

        $active = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 2,
            'departure_date' => now()->subDay(),
            'arrival_date' => now()->addDay(),
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-D2-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        // Link user rentals to both shipments so they appear in the fleet query.
        foreach ([$scheduledShip, $active] as $ship) {
            $c = $mkContainer();
            $r = Rental::query()->create([
                'user_id' => $user->id,
                'container_id' => $c->id,
                'route_id' => $route->id,
                'origin_port_id' => $origin->id,
                'destination_port_id' => $destination->id,
                'start_date' => now()->subDay(),
                'end_date' => now()->addWeeks(3),
                'status' => 'in_progress',
                'payment_status' => 'paid',
                'terms_accepted' => true,
                'price' => 10.00,
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $r->id,
                'loaded_at' => now(),
                'condition_on_arrival' => 'good',
                'notes' => null,
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $fleet = $response->json('vessel_positions');
        $this->assertCount(1, $fleet);
        $this->assertSame($active->id, $fleet[0]['shipment_id']);
        $this->assertSame('in_transit', $fleet[0]['shipment_status']);
    }

    public function test_rental_cargo_count_sums_multiple_items_on_same_shipment(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandMulti',
            'iso_code' => 'MM',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Multi Owner',
            'email' => 'multi-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000030',
        ]);

        $origin = Port::query()->create([
            'name' => 'Multi Origin',
            'city' => 'MO',
            'country_id' => $countryId,
            'latitude' => 20.0,
            'longitude' => 21.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Multi Dest',
            'city' => 'MD',
            'country_id' => $countryId,
            'latitude' => 22.0,
            'longitude' => 23.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 2,
            'distance' => 50.0,
            'route_status' => 'open',
        ]);

        $c1 = Container::query()->create([
            'serial_number' => 'M1-'.uniqid(),
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
        $c2 = Container::query()->create([
            'serial_number' => 'M2-'.uniqid(),
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

        $vessel = Vessel::query()->create([
            'name' => 'Multi Vessel',
            'imo_number' => 'MUL'.substr(str_replace('.', '', uniqid('', true)), 0, 11),
            'capacity_teu' => 400,
            'status' => 'in_transit',
            'current_port_id' => $origin->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        $shipment = Shipment::query()->create([
            'vessel_id' => $vessel->id,
            'route_id' => $route->id,
            'leg_sequence' => 1,
            'departure_date' => now()->subHours(3),
            'arrival_date' => now()->addDay(),
            'actual_departure_date' => null,
            'actual_arrival_date' => null,
            'port_operations_until' => null,
            'tracking_number' => 'TRK-M-'.strtoupper(uniqid()),
            'status' => 'in_transit',
        ]);

        $u = User::factory()->create(['country_id' => $countryId]);
        $r1 = Rental::query()->create([
            'user_id' => $u->id,
            'container_id' => $c1->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 'in_progress',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 10.00,
        ]);
        $r2 = Rental::query()->create([
            'user_id' => $u->id,
            'container_id' => $c2->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 11.00,
        ]);

        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $c1->id,
            'rental_id' => $r1->id,
            'loaded_at' => now(),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);
        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $c2->id,
            'rental_id' => $r2->id,
            'loaded_at' => now(),
            'condition_on_arrival' => 'good',
            'notes' => null,
        ]);

        $response = $this->actingAs($u)->getJson(route('rentals.map-data'));

        $response->assertOk();
        $fleet = $response->json('vessel_positions');
        $this->assertCount(1, $fleet);
        $this->assertSame(2, $fleet[0]['rental_cargo_count']);
        $this->assertTrue($fleet[0]['has_rental_cargo']);
    }

    public function test_bilbao_vigo_route_has_offshore_sea_path_after_seeders(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(PortSeeder::class);
        $this->seed(RouteSeeder::class);

        $raw = DB::table('routes')
            ->join('ports as o', 'o.id', '=', 'routes.origin_port_id')
            ->join('ports as d', 'd.id', '=', 'routes.destination_port_id')
            ->where('o.name', 'Port of Bilbao')
            ->where('d.name', 'Port of Vigo')
            ->value('routes.sea_path');

        $this->assertNotNull($raw);
        $decoded = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : $raw;
        $this->assertIsArray($decoded);
        $this->assertGreaterThanOrEqual(3, count($decoded));
    }

    public function test_map_includes_vessel_when_current_port_not_on_logistics_whitelist(): void
    {
        config(['logistics_map.port_names' => ['Port of Rotterdam']]);

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'WhitelistTestLand',
            'iso_code' => 'WL',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Port::query()->create([
            'name' => 'Port of Rotterdam',
            'city' => 'Rotterdam',
            'country_id' => $countryId,
            'latitude' => 51.9244,
            'longitude' => 4.4777,
        ]);

        $offListPort = Port::query()->create([
            'name' => 'Off List Test Port',
            'city' => 'X',
            'country_id' => $countryId,
            'latitude' => 51.5,
            'longitude' => 3.8,
        ]);

        Vessel::query()->create([
            'name' => 'Off Whitelist Vessel',
            'imo_number' => 'OWL'.substr(str_replace('.', '', uniqid('', true)), 0, 10),
            'capacity_teu' => 100,
            'status' => 'in_port',
            'current_port_id' => $offListPort->id,
            'last_inspection_date' => now()->subMonth()->toDateString(),
        ]);

        // Regular users: idle vessels are never shown regardless of whitelist config.
        $user = User::factory()->create(['country_id' => $countryId]);
        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();
        $names = collect($response->json('vessel_positions'))->pluck('vessel_name')->all();
        $this->assertNotContains('Off Whitelist Vessel', $names);

        // User-only: admin role does not broaden visibility on this endpoint.
        $opsUser = User::factory()->create(['country_id' => $countryId, 'role' => 'admin']);
        $opsResponse = $this->actingAs($opsUser)->getJson(route('rentals.map-data'));
        $opsResponse->assertOk();
        $opsNames = collect($opsResponse->json('vessel_positions'))->pluck('vessel_name')->all();
        $this->assertNotContains('Off Whitelist Vessel', $opsNames);

        $portNames = collect($response->json('ports'))->pluck('name')->all();
        $this->assertContains('Port of Rotterdam', $portNames);
        $this->assertNotContains('Off List Test Port', $portNames);
    }

    public function test_map_positions_include_rental_with_stale_updated_at_when_many_active_rentals_exist(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandManyRentals',
            'iso_code' => 'MMR',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'Many Owner',
            'email' => 'many-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000999',
        ]);

        $origin = Port::query()->create([
            'name' => 'Many Origin '.uniqid(),
            'city' => 'MO',
            'country_id' => $countryId,
            'latitude' => 1.0,
            'longitude' => 2.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'Many Dest '.uniqid(),
            'city' => 'MD',
            'country_id' => $countryId,
            'latitude' => 3.0,
            'longitude' => 4.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $user = User::factory()->create(['country_id' => $countryId]);

        $mkRental = function () use ($user, $owner, $origin, $destination, $route): void {
            $container = Container::query()->create([
                'serial_number' => 'MANY-'.str_replace('.', '', uniqid('', true)),
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
            Rental::query()->create([
                'user_id' => $user->id,
                'container_id' => $container->id,
                'route_id' => $route->id,
                'origin_port_id' => $origin->id,
                'destination_port_id' => $destination->id,
                'start_date' => now()->subHour(),
                'end_date' => now()->addDays(3),
                'status' => 'active',
                'payment_status' => 'paid',
                'terms_accepted' => true,
                'price' => 10.00,
            ]);
        };

        for ($i = 0; $i < 41; $i++) {
            $mkRental();
        }

        $staleContainer = Container::query()->create([
            'serial_number' => 'STALE-MAP-'.str_replace('.', '', uniqid('', true)),
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
        $staleRental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $staleContainer->id,
            'route_id' => $route->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(3),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 11.00,
        ]);

        DB::table('rentals')->where('id', $staleRental->id)->update([
            'updated_at' => now()->subYears(3),
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();
        $ids = collect($response->json('positions'))->pluck('rental_id')->all();
        $this->assertContains($staleRental->id, $ids);
        $this->assertGreaterThanOrEqual(42, count($ids));
    }

    public function test_map_position_resolves_ports_from_route_when_rental_port_ids_null(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'MaplandRoutePorts',
            'iso_code' => 'MRP',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'RP Owner',
            'email' => 'rp-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000998',
        ]);

        $origin = Port::query()->create([
            'name' => 'RP Origin '.uniqid(),
            'city' => 'RPO',
            'country_id' => $countryId,
            'latitude' => 11.0,
            'longitude' => 12.0,
        ]);
        $destination = Port::query()->create([
            'name' => 'RP Dest '.uniqid(),
            'city' => 'RPD',
            'country_id' => $countryId,
            'latitude' => 21.0,
            'longitude' => 22.0,
        ]);

        $route = ShippingRoute::query()->create([
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_days' => 4,
            'distance' => 100.0,
            'route_status' => 'open',
        ]);

        $container = Container::query()->create([
            'serial_number' => 'RP-'.uniqid(),
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

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => $route->id,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(2),
            'status' => 'active',
            'payment_status' => 'paid',
            'terms_accepted' => true,
            'price' => 9.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('rentals.map-data'));
        $response->assertOk();
        $row = collect($response->json('positions'))->firstWhere('rental_id', $rental->id);
        $this->assertNotNull($row);
        $this->assertNotNull($row['latitude']);
        $this->assertNotNull($row['longitude']);
    }
}
