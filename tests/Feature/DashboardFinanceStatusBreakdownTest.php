<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardFinanceStatusBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Testland',
            'iso_code' => 'TT',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    private function createRentalWithTx(User $user, Container $container, Port $port, string $rentalStatus, string $paymentStatus, string $txStatus, float $amount): Rental
    {
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => $rentalStatus,
            'payment_status' => $paymentStatus,
            'price' => $amount,
        ]);

        Transaction::query()->create([
            'rental_id' => $rental->id,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => $txStatus,
            'external_provider_id' => 'test:'.$txStatus.':'.$rental->id,
            'transaction_date' => now(),
            'payment_method' => 'card',
        ]);

        return $rental;
    }

    public function test_user_dashboard_and_finance_are_filtered_and_include_breakdowns(): void
    {
        $base = $this->seedBase();

        $userA = User::factory()->create(['country_id' => $base['countryId']]);
        $userB = User::factory()->create(['country_id' => $base['countryId']]);

        $this->createRentalWithTx($userA, $base['container'], $base['port'], 'approved', 'pending', 'pending', 100);
        $this->createRentalWithTx($userA, $base['container'], $base['port'], 'rejected', 'failed', 'failed', 200);
        $this->createRentalWithTx($userB, $base['container'], $base['port'], 'approved', 'paid', 'completed', 999);

        $this->actingAs($userA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('transactionsByStatus')
                ->has('rentalsByStatus')
                ->has('rentalsByPaymentStatus')
                ->has('rejectedApproval')
                ->where('transactionsByStatus.pending.count', 1)
                ->where('transactionsByStatus.failed.count', 1)
                ->missing('transactionsByStatus.completed')
                ->where('rentalsByStatus.approved.count', 1)
                ->where('rentalsByStatus.rejected.count', 1)
                ->where('rentalsByPaymentStatus.pending.count', 1)
                ->missing('rentalsByPaymentStatus.paid')
                ->where('rejectedApproval.count', 0)
            );

        $this->actingAs($userA)
            ->get(route('finance.monitoring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FinanceMonitoring')
                ->has('transactionsByStatus')
                ->has('rentalsByStatus')
                ->has('rentalsByPaymentStatus')
                ->has('rejectedApproval')
                ->where('transactionsByStatus.pending.count', 1)
                ->where('transactionsByStatus.failed.count', 1)
                ->missing('transactionsByStatus.completed')
                ->where('rejectedApproval.count', 0)
            );
    }

    public function test_admin_dashboard_and_finance_are_global_and_include_breakdowns(): void
    {
        $base = $this->seedBase();

        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
        $user = User::factory()->create(['country_id' => $base['countryId']]);

        $this->createRentalWithTx($user, $base['container'], $base['port'], 'approved', 'paid', 'completed', 100);
        $this->createRentalWithTx($user, $base['container'], $base['port'], 'pending_approval', 'unpaid', 'pending', 200);
        $this->createRentalWithTx($user, $base['container'], $base['port'], 'rejected', 'failed', 'cancelled', 300);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('stats.transactionsByStatus')
                ->has('stats.rentalsByStatus')
                ->has('stats.rentalsByPaymentStatus')
                ->where('stats.transactionsByStatus.completed.count', 1)
                ->where('stats.transactionsByStatus.pending.count', 1)
                ->where('stats.transactionsByStatus.cancelled.count', 1)
            );

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('transactionsByStatus')
                ->has('rentalsByStatus')
                ->has('rentalsByPaymentStatus')
                ->where('transactionsByStatus.completed.count', 1)
                ->where('transactionsByStatus.pending.count', 1)
                ->where('transactionsByStatus.cancelled.count', 1)
                ->where('rentalsByStatus.rejected.count', 1)
                ->where('rentalsByStatus.pending_approval.count', 1)
            );
    }
}
