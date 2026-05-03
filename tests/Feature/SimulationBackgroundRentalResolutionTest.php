<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Country;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use App\Services\SimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationBackgroundRentalResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{container: Container, port: Port, user: User}
     */
    private function createContainerPortUser(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'SimRent '.uniqid(),
            'iso_code' => 'S'.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;
        $owner = Owner::query()->create([
            'name' => 'Sim Owner',
            'email' => 'sim-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000005',
        ]);
        $port = Port::query()->create([
            'name' => 'Sim Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'SIMR-'.uniqid(),
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

        return ['container' => $container, 'port' => $port, 'user' => $user];
    }

    public function test_background_tick_ignores_future_scheduled_rental_in_favor_of_current(): void
    {
        extract($this->createContainerPortUser());
        /** @var Container $container */
        /** @var Port $port */
        /** @var User $user */
        $future = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonths(2),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'scheduled',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $current = Rental::query()->create([
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

        app(SimulationService::class)->tickContainer($container);

        $this->assertNotSame($future->id, $current->id);
        $metricRentalIds = Metric::query()
            ->where('container_id', $container->id)
            ->distinct()
            ->pluck('rental_id')
            ->filter()
            ->values()
            ->all();
        $this->assertSame([(int) $current->id], array_map('intval', $metricRentalIds));
    }

    public function test_background_tick_uses_snapshot_rental_when_still_valid(): void
    {
        extract($this->createContainerPortUser());

        $older = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $newer = Rental::query()->create([
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

        ContainerSimulationSnapshot::query()->updateOrCreate(
            ['container_id' => $container->id],
            [
                'rental_id' => $older->id,
                'sensor_state' => [],
                'actuators' => null,
                'last_tick_at' => null,
            ]
        );

        app(SimulationService::class)->tickContainer($container);

        $metricRentalIds = Metric::query()
            ->where('container_id', $container->id)
            ->distinct()
            ->pluck('rental_id')
            ->filter()
            ->values()
            ->all();
        $this->assertSame([(int) $older->id], array_map('intval', $metricRentalIds));
    }

    public function test_background_tick_falls_back_when_snapshot_rental_expired(): void
    {
        extract($this->createContainerPortUser());

        $expired = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->subMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $current = Rental::query()->create([
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

        ContainerSimulationSnapshot::query()->updateOrCreate(
            ['container_id' => $container->id],
            [
                'rental_id' => $expired->id,
                'sensor_state' => [],
                'actuators' => null,
                'last_tick_at' => null,
            ]
        );

        app(SimulationService::class)->tickContainer($container);

        $metricRentalIds = Metric::query()
            ->where('container_id', $container->id)
            ->distinct()
            ->pluck('rental_id')
            ->filter()
            ->values()
            ->all();
        $this->assertSame([(int) $current->id], array_map('intval', $metricRentalIds));
    }

    public function test_background_tick_runs_for_approved_rental_before_start_date(): void
    {
        extract($this->createContainerPortUser());

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'approved',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        app(SimulationService::class)->tickContainer($container);

        $this->assertGreaterThan(0, Metric::query()->where('container_id', $container->id)->count());
        $this->assertNotNull(
            ContainerSimulationSnapshot::query()->where('container_id', $container->id)->value('last_tick_at')
        );
    }

    public function test_background_tick_writes_no_metrics_when_only_pending_approval_rental(): void
    {
        extract($this->createContainerPortUser());

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
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        app(SimulationService::class)->tickContainer($container);

        $this->assertSame(
            0,
            Metric::query()->where('container_id', $container->id)->count()
        );
    }
}
