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

class RejectedByApprovalFinanceTest extends TestCase
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

    public function test_backfill_marks_rejected_by_approval_and_finance_counts_it(): void
    {
        $base = $this->seedBase();

        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'pending',
            'rejection_reason' => 'APPROVAL_REJECTED: denied by operator',
            'price' => 1500.00,
        ]);

        Transaction::query()->create([
            'rental_id' => $rental->id,
            'amount' => 123.45,
            'currency' => 'USD',
            'status' => 'pending',
            'external_provider_id' => 'test:pending:'.$rental->id,
            'transaction_date' => now(),
            'payment_method' => 'card',
        ]);

        $this->artisan('rentals:backfill-approval-reject-payment-status')->assertSuccessful();

        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'payment_status' => 'rejected_by_approval',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('rejectedApproval')
                ->where('rejectedApproval.count', 1)
                ->where('rejectedApproval.lostRevenuePriceSum', 1500)
                ->where('rejectedApproval.txAmountSum', 123.45)
            );
    }

    public function test_rejected_by_approval_without_transaction_appears_as_synthetic_failed_and_counts_in_overview(): void
    {
        $base = $this->seedBase();

        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'rejected_by_approval',
            'rejection_reason' => 'APPROVAL_REJECTED: denied by operator',
            'reviewed_at' => now(),
            'price' => 1500.00,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('syntheticTransactions', 1)
                ->where('syntheticTransactions.0.rental_id', $rental->id)
                ->where('syntheticTransactions.0.status', 'failed')
                ->where('syntheticTransactions.0.rental_payment_status', 'rejected_by_approval')
                ->where('overview.failedCount', 1)
                ->where('overview.totalTransactions', 1)
                ->where('overview.failedAmount', 1500)
            );

        $this->actingAs($user)
            ->get(route('finance.monitoring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FinanceMonitoring')
                ->has('syntheticTransactions', 1)
                ->where('syntheticTransactions.0.rental_id', $rental->id)
                ->where('syntheticTransactions.0.status', 'failed')
                ->where('syntheticTransactions.0.rental_payment_status', 'rejected_by_approval')
                ->where('overview.failedCount', 1)
                ->where('overview.totalTransactions', 1)
                ->where('overview.failedAmount', 1500)
            );
    }

    public function test_backfill_marks_reviewed_rejected_rental_as_rejected_by_approval_even_without_prefix(): void
    {
        $base = $this->seedBase();
        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $base['container']->id,
            'route_id' => null,
            'origin_port_id' => $base['port']->id,
            'destination_port_id' => $base['port']->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'rejected',
            'payment_status' => 'pending',
            'rejection_reason' => 'Denied by operator',
            'reviewed_at' => now(),
            'price' => 100.00,
        ]);

        $this->artisan('rentals:backfill-approval-reject-payment-status')->assertSuccessful();

        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'payment_status' => 'rejected_by_approval',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('rejectedApproval')
                ->where('rejectedApproval.count', 1)
            );
    }

    public function test_pending_approval_without_transaction_appears_as_synthetic_pending_and_counts_in_overview(): void
    {
        $base = $this->seedBase();

        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $rental = Rental::query()->create([
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

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('syntheticTransactions')
                ->where('syntheticTransactions.0.rental_id', $rental->id)
                ->where('syntheticTransactions.0.status', 'pending')
                ->where('syntheticTransactions.0.payment_method', 'approval_pending')
                ->where('syntheticTransactions.0.rental_status', 'pending_approval')
                ->where('overview.pendingCount', 1)
                ->where('overview.pendingAmount', 200)
                ->where('overview.totalTransactions', 1)
            );

        $this->actingAs($user)
            ->get(route('finance.monitoring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FinanceMonitoring')
                ->has('syntheticTransactions')
                ->where('syntheticTransactions.0.rental_id', $rental->id)
                ->where('syntheticTransactions.0.status', 'pending')
                ->where('syntheticTransactions.0.payment_method', 'approval_pending')
                ->where('syntheticTransactions.0.rental_status', 'pending_approval')
                ->where('overview.pendingCount', 1)
                ->where('overview.pendingAmount', 200)
                ->where('overview.totalTransactions', 1)
            );
    }
}
