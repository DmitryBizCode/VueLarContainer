<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RentalsCenterOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{countryId: int, port: Port, container: Container, user: User} */
    private function baseUserWithContainer(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'RCLand',
            'iso_code' => 'RC',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = Owner::query()->create([
            'name' => 'RC Owner',
            'email' => 'rc-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000001',
        ]);

        $port = Port::query()->create([
            'name' => 'RC Port',
            'city' => 'City',
            'country_id' => $countryId,
        ]);

        $container = Container::query()->create([
            'serial_number' => 'RC-'.uniqid(),
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

        $user = User::factory()->create(['country_id' => $countryId]);

        return [
            'countryId' => $countryId,
            'port' => $port,
            'container' => $container,
            'user' => $user,
        ];
    }

    public function test_overview_counts_approved_toward_upcoming_not_active_and_excludes_draft(
    ): void {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $b = $this->baseUserWithContainer();

        Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-16 10:00:00'),
            'end_date' => Carbon::parse('2026-04-25 10:00:00'),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'approved',
            'payment_status' => 'unpaid',
            'price' => 1000,
        ]);

        Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-20 10:00:00'),
            'end_date' => Carbon::parse('2026-04-28 10:00:00'),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'price' => 2000,
        ]);

        $this->actingAs($b['user'])
            ->get(route('rentals.center'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('overview.activeCount', 0)
                    ->where('overview.upcomingStartsCount', 1)
            );

        Carbon::setTestNow();
    }

    public function test_default_list_hides_pending_rentals_scope_all_shows_them(): void
    {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $b = $this->baseUserWithContainer();

        $pending = Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-20 10:00:00'),
            'end_date' => Carbon::parse('2026-04-28 10:00:00'),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'pending_approval',
            'payment_status' => 'unpaid',
            'price' => 100,
        ]);

        $this->actingAs($b['user'])
            ->get(route('rentals.center'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('filters.scope', 'successful')
                    ->where('rentals.total', 0)
            );

        $this->actingAs($b['user'])
            ->get(route('rentals.center', ['scope' => 'all']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('filters.scope', 'all')
                    ->where('rentals.total', 1)
                    ->where('rentals.data.0.id', $pending->id)
            );

        Carbon::setTestNow();
    }

    public function test_overdue_excludes_cancelled_rejected_approval_payments_and_ended_rentals(): void
    {
        Carbon::setTestNow('2026-04-20 10:00:00');

        $b = $this->baseUserWithContainer();

        Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-10'),
            'end_date' => Carbon::parse('2026-04-19'),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
            'price' => 500,
        ]);

        Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-10'),
            'end_date' => Carbon::parse('2026-04-19'),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'price' => 100,
        ]);

        Rental::query()->create([
            'user_id' => $b['user']->id,
            'container_id' => $b['container']->id,
            'route_id' => null,
            'origin_port_id' => $b['port']->id,
            'destination_port_id' => $b['port']->id,
            'start_date' => Carbon::parse('2026-04-10'),
            'end_date' => Carbon::parse('2026-04-19'),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'rejected_by_approval',
            'price' => 200,
        ]);

        $this->actingAs($b['user'])
            ->get(route('rentals.center'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('overview.overduePaymentsCount', 1)
            );

        Carbon::setTestNow();
    }
}
