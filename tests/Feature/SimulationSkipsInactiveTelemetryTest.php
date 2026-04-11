<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimulationSkipsInactiveTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function seedIotRental(bool $telemetryActive): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Sleepland '.uniqid(),
            'iso_code' => 'S'.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Sleep Owner',
            'email' => 'sleep-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000009',
        ]);
        $port = Port::query()->create([
            'name' => 'Sleep Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'SLEEP-'.uniqid(),
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
        Rental::query()->create([
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
            'is_telemetry_active' => $telemetryActive,
        ]);
    }

    public function test_worker_writes_no_metrics_when_telemetry_paused(): void
    {
        $this->seedIotRental(false);

        $this->artisan('simulation:worker', ['--once' => true])
            ->assertSuccessful();

        $this->assertSame(0, Metric::query()->count());
    }

    public function test_worker_writes_metrics_when_telemetry_active(): void
    {
        $this->seedIotRental(true);

        $this->artisan('simulation:worker', ['--once' => true])
            ->assertSuccessful();

        $this->assertGreaterThan(0, Metric::query()->count());
    }
}
