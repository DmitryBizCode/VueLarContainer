<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationWorkerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function createIotContainer(): Container
    {
        $countryId = Country::factory()->create([
            'name' => 'Workerland '.uniqid(),
            'iso_code' => 'W'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;
        $owner = Owner::query()->create([
            'name' => 'Worker Owner',
            'email' => 'worker-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000001',
        ]);
        $port = Port::query()->create([
            'name' => 'Worker Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);

        return Container::query()->create([
            'serial_number' => 'WORKER-'.uniqid(),
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
    }

    public function test_worker_once_ticks_iot_containers_and_writes_telemetry(): void
    {
        $container = $this->createIotContainer();
        $port = $container->currentPort;
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
        ]);

        $this->artisan('simulation:worker', ['--once' => true])
            ->assertSuccessful();

        $this->assertGreaterThan(0, Metric::query()->count());
    }
}
