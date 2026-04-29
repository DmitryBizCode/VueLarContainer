<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardPendingApprovalFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'AdminDashLand',
            'iso_code' => 'AD',
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

    public function test_admin_dashboard_pending_amount_includes_pending_approval_rental_without_transaction(): void
    {
        $base = $this->seedBase();

        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $base['countryId']]);
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

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.pendingCount', 1)
                ->where('stats.pendingAmount', 200)
                ->where('stats.totalTransactions', 1)
            );
    }
}
