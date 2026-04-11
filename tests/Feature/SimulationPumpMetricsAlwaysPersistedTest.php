<?php

namespace Tests\Feature;

use App\DataTransferObjects\ActuatorInputDto;
use App\Models\Container;
use App\Models\ContainerSensor;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\SensorType;
use App\Models\User;
use App\Services\SimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimulationPumpMetricsAlwaysPersistedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * When only e.g. temperature is linked in container_sensors, pump_running must still reach metrics
     * (otherwise DB stays 0 while snapshot shows the real actuator / auto-pump state).
     */
    public function test_pump_running_written_when_not_in_container_sensor_telemetry_keys(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Pumpland',
            'iso_code' => 'PL',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Pump Owner',
            'email' => 'pump-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000008',
        ]);
        $port = Port::query()->create([
            'name' => 'Pump Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'PUMP-'.uniqid(),
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
            'is_telemetry_active' => true,
        ]);

        $tempType = SensorType::query()->create([
            'slug' => 'temp_only_'.uniqid(),
            'name' => 'Temperature only',
            'category' => 'climate',
            'is_optional' => true,
            'telemetry_keys' => ['temperature_c'],
            'sort_order' => 1,
        ]);
        ContainerSensor::query()->create([
            'container_id' => $container->id,
            'sensor_type_id' => $tempType->id,
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $initial = config('simulation.initial', []);
        $snapshot = ContainerSimulationSnapshot::query()->firstOrNew(['container_id' => $container->id]);
        $snapshot->rental_id = $rental->id;
        $snapshot->sensor_state = array_merge($initial, [
            'water_level_pct' => 85.0,
            'pump_running' => 0.0,
        ]);
        $snapshot->actuators = ActuatorInputDto::fromArray(['pump' => true])->toArray();
        $snapshot->last_tick_at = now()->subMinute();
        $snapshot->save();

        app(SimulationService::class)->tickContainer($container->fresh(['containerSensors.sensorType']), $rental);

        $pump = Metric::query()
            ->where('container_id', $container->id)
            ->where('type', 'pump_running')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($pump);
        $this->assertSame(1.0, (float) $pump->value);

        $this->assertSame(
            0,
            Metric::query()->where('container_id', $container->id)->where('type', 'co2_ppm')->count(),
            'optional keys not in container_sensors should still be omitted'
        );
    }
}
