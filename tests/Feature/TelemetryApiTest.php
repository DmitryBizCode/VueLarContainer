<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createRentalWithContainer(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Testland '.uniqid(),
            'iso_code' => 'T'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Test Owner',
            'email' => 'owner@test.local',
            'phone_number' => '+1000000000',
        ]);
        $port = Port::query()->create([
            'name' => 'Test Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'TEST-API-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        return [$user, $rental, $container];
    }

    public function test_telemetry_requires_authentication(): void
    {
        [, $rental] = $this->createRentalWithContainer();

        $this->getJson("/api/rentals/{$rental->id}/telemetry")
            ->assertUnauthorized();
    }

    public function test_telemetry_forbidden_for_other_user(): void
    {
        [, $rental] = $this->createRentalWithContainer();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/rentals/{$rental->id}/telemetry")
            ->assertForbidden();
    }

    public function test_telemetry_falls_back_to_simulation_snapshot_when_metrics_empty(): void
    {
        [$user, $rental, $container] = $this->createRentalWithContainer();
        Sanctum::actingAs($user);

        ContainerSimulationSnapshot::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'sensor_state' => [
                'temperature_c' => 7.25,
                'humidity_rh' => 55.0,
            ],
            'actuators' => [],
            'last_tick_at' => now(),
        ]);

        $this->getJson("/api/rentals/{$rental->id}/telemetry")
            ->assertOk()
            ->assertJsonPath('sensors.temperature_c', 7.25)
            ->assertJsonPath('sensors.humidity_rh', 55);
    }

    public function test_telemetry_returns_latest_readings_for_owner(): void
    {
        [$user, $rental, $container] = $this->createRentalWithContainer();
        Sanctum::actingAs($user);

        $now = CarbonImmutable::now();
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'temperature_c',
            'value' => 5.5,
            'unit' => '°C',
            'recorded_at' => $now,
        ]);
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'humidity_rh',
            'value' => 60,
            'unit' => '%',
            'recorded_at' => $now,
        ]);

        $response = $this->getJson("/api/rentals/{$rental->id}/telemetry");
        $response->assertOk()
            ->assertJsonPath('sensors.temperature_c', 5.5)
            ->assertJsonPath('sensors.humidity_rh', 60);
    }

    public function test_monitor_charts_includes_iot_latest_for_polling(): void
    {
        [$user, $rental, $container] = $this->createRentalWithContainer();
        Sanctum::actingAs($user);

        $now = CarbonImmutable::now();
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'temperature_c',
            'value' => 3.3,
            'unit' => '°C',
            'recorded_at' => $now,
        ]);

        $from = $now->subHour()->toIso8601String();
        $to = $now->addHour()->toIso8601String();
        $url = route('api.rentals.monitor-charts', $rental).'?'.http_build_query([
            'from' => $from,
            'to' => $to,
        ]);

        $response = $this->getJson($url);
        $response->assertOk()
            ->assertJsonPath('series_mode', 'window')
            ->assertJsonStructure(['iot_latest' => ['sensors', 'recorded_at']])
            ->assertJsonPath('iot_latest.sensors.temperature_c', 3.3);
    }
}
