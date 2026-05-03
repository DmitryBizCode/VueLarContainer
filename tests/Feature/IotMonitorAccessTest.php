<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IotMonitorAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, rental: Rental, container: Container}
     */
    private function createPendingRentalForUser(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'IotGate '.uniqid(),
            'iso_code' => 'I'.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;
        $owner = Owner::query()->create([
            'name' => 'Gate Owner',
            'email' => 'gate-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000006',
        ]);
        $port = Port::query()->create([
            'name' => 'Gate Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'GATE-'.uniqid(),
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
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        return ['user' => $user, 'rental' => $rental, 'container' => $container];
    }

    /**
     * @return array{user: User, rental: Rental}
     */
    private function createCompletedRentalForUser(): array
    {
        $data = $this->createPendingRentalForUser();
        $data['rental']->update([
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        return ['user' => $data['user'], 'rental' => $data['rental']->fresh()];
    }

    public function test_monitor_page_forbidden_when_rental_pending_approval(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createPendingRentalForUser();

        $this->actingAs($user)
            ->get(route('rentals.monitor', $rental))
            ->assertForbidden();
    }

    public function test_monitor_charts_api_forbidden_when_rental_pending_approval(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createPendingRentalForUser();
        Sanctum::actingAs($user);

        $this->getJson("/api/rentals/{$rental->id}/monitor-charts")
            ->assertForbidden();
    }

    public function test_monitor_page_ok_when_rental_completed(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalForUser();

        $this->actingAs($user)
            ->get(route('rentals.monitor', $rental))
            ->assertOk();
    }

    public function test_monitor_charts_api_ok_when_rental_completed(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalForUser();
        Sanctum::actingAs($user);

        $this->getJson("/api/rentals/{$rental->id}/monitor-charts")
            ->assertOk();
    }

    public function test_monitor_charts_web_poll_uses_session_and_ok_when_rental_completed(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalForUser();

        $this->actingAs($user)
            ->getJson(route('rentals.monitor.charts-data', $rental))
            ->assertOk()
            ->assertJsonStructure(['sensors', 'door_events', 'date_from', 'date_to']);
    }

    public function test_monitor_charts_web_poll_forbidden_when_rental_pending_approval(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createPendingRentalForUser();

        $this->actingAs($user)
            ->getJson(route('rentals.monitor.charts-data', $rental))
            ->assertForbidden();
    }

    // --- completed rental with PAST end_date (realistic scenario) ---

    /**
     * @return array{user: User, rental: Rental}
     */
    private function createCompletedRentalWithPastEndDateForUser(): array
    {
        $data = $this->createPendingRentalForUser();
        $data['rental']->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'end_date' => now()->subDay(),
        ]);

        return ['user' => $data['user'], 'rental' => $data['rental']->fresh()];
    }

    public function test_monitor_page_ok_when_rental_completed_and_end_date_past(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalWithPastEndDateForUser();

        $this->actingAs($user)
            ->get(route('rentals.monitor', $rental))
            ->assertOk();
    }

    public function test_monitor_charts_api_ok_when_rental_completed_and_end_date_past(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalWithPastEndDateForUser();
        Sanctum::actingAs($user);

        $this->getJson("/api/rentals/{$rental->id}/monitor-charts")
            ->assertOk();
    }

    public function test_telemetry_toggle_forbidden_when_rental_completed_and_end_date_past(): void
    {
        ['user' => $user, 'rental' => $rental] = $this->createCompletedRentalWithPastEndDateForUser();
        Sanctum::actingAs($user);

        $this->postJson(route('api.rentals.toggle-telemetry', $rental))
            ->assertForbidden();
    }
}
