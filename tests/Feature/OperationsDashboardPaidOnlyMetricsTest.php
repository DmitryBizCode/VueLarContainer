<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsDashboardPaidOnlyMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'Testland',
            'iso_code' => 'TT',
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

    public function test_dashboard_last_transaction_prefers_rejected_by_approval_event(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $paidRental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(3),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'price' => 100,
        ]);

        Transaction::query()->create([
            'rental_id' => $paidRental->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
            'external_provider_id' => 'test:paid:'.$paidRental->id,
            'transaction_date' => now()->subDays(4),
            'payment_method' => 'card',
        ]);

        $rejectRental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(3),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'rejected_by_approval',
            'rejection_reason' => 'Denied',
            'price' => 50,
            'updated_at' => now()->subHour(),
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // Controller may pass DB raw datetime string for MAX(updated_at).
                ->where('financialOverview.lastTransactionAt', $rejectRental->updated_at->format('Y-m-d H:i:s'))
            );
    }

    public function test_dashboard_recent_rental_and_completed_count_use_paid_only_rule(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(3),
            'rental_days' => 5,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'pending',
            'price' => 10,
        ]);

        $paidCompleted = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(3),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'price' => 123,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.completedRentals', 1)
                ->where('recentRental.id', $paidCompleted->id)
                ->where('recentRental.payment_status', 'paid')
            );
    }

    public function test_dashboard_completed_rentals_counts_paid_rentals_past_end_date_even_if_status_not_completed(): void
    {
        $base = $this->seedBase();
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
            'rental_days' => 9,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 123,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.completedRentals', 1)
            );
    }

    public function test_dashboard_pending_amount_includes_pending_approval_rental_without_transaction(): void
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
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'price' => 200.00,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('financialOverview.pendingCount', 1)
                ->where('financialOverview.pendingAmount', 200)
            );
    }
}
