<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalTelemetryToggleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Rental}
     */
    protected function createActiveIotRental(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Toggleland '.uniqid(),
            'iso_code' => 'G'.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Toggle Owner',
            'email' => 'toggle-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000007',
        ]);
        $port = Port::query()->create([
            'name' => 'Toggle Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'TOG-'.uniqid(),
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

        return [$user, $rental];
    }

    public function test_toggle_requires_authentication(): void
    {
        [, $rental] = $this->createActiveIotRental();

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertUnauthorized();
    }

    public function test_toggle_forbidden_for_other_user(): void
    {
        [, $rental] = $this->createActiveIotRental();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertNotFound();
    }

    public function test_owner_can_toggle_telemetry_twice(): void
    {
        [$user, $rental] = $this->createActiveIotRental();
        Sanctum::actingAs($user);

        $this->assertTrue($rental->is_telemetry_active);

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertOk()
            ->assertJson(['is_telemetry_active' => false]);

        $rental->refresh();
        $this->assertFalse($rental->is_telemetry_active);

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertOk()
            ->assertJson(['is_telemetry_active' => true]);

        $this->assertTrue($rental->fresh()->is_telemetry_active);
    }

    public function test_toggle_forbidden_after_rental_end_date_passed(): void
    {
        [$user, $rental] = $this->createActiveIotRental();
        $rental->end_date = now()->subDay();
        $rental->status = 'completed';
        $rental->save();

        Sanctum::actingAs($user);

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertForbidden();
    }
}
