<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsDashboardBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'Blocksland',
            'iso_code' => 'BL',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000000',
        ]);

        $port = Port::query()->create([
            'name' => 'Port',
            'city' => 'City',
            'country_id' => $countryId,
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
            'current_port_id' => $port->id,
        ]);

        return compact('countryId', 'port', 'container');
    }

    public function test_active_rentals_excludes_pending_approval_and_counts_paid_only(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'price' => 10,
        ]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.activeRentals', 1)
            );
    }

    public function test_unread_notifications_includes_derived_notifications(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'approved',
            'payment_status' => 'paid',
            'price' => 99,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.unreadNotifications', 2)
                ->has('latestNotifications')
            );
    }

    public function test_upcoming_milestones_includes_rental_start_events(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(8),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'scheduled',
            'payment_status' => 'paid',
            'price' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('upcomingMilestones')
                ->where('upcomingMilestones.0.type', 'start')
            );
    }

    public function test_mark_all_notifications_read_returns_redirect_for_inertia_request(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Test',
            'message' => 'Test message',
            'type' => 'info',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('notifications.read-all'))
            ->assertStatus(303);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'is_read' => 1,
        ]);
    }
}
