<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Country;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RentalLedgerTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLedgerTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function seedRentalEligibleForPaymentApproval(): array
    {
        $countryId = Country::factory()->create([
            'name' => 'Ledgerland',
            'iso_code' => 'LG',
            'phone_code' => '+0',
            'interest_tax' => 0,
        ])->id;

        $owner = Owner::query()->create([
            'name' => 'Ledger Owner',
            'email' => 'ledger-owner-'.uniqid().'@test.local',
            'phone_number' => '+1000000001',
        ]);

        $port = Port::query()->create([
            'name' => 'Ledger Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);

        $container = Container::query()->create([
            'serial_number' => 'LED-'.uniqid(),
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

        $client = User::factory()->create(['country_id' => $countryId]);
        $admin = User::factory()->create(['role' => 'admin', 'country_id' => $countryId]);

        $rental = Rental::query()->create([
            'user_id' => $client->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'rental_days' => 7,
            'cargo_types' => ['general'],
            'status' => 'approved',
            'payment_status' => 'pending',
            'price' => 1500.00,
            'payment_approved_at' => null,
            'payment_approved_by' => null,
        ]);

        return ['admin' => $admin, 'rental' => $rental, 'client' => $client];
    }

    public function test_approve_payment_creates_synthetic_transaction(): void
    {
        $ctx = $this->seedRentalEligibleForPaymentApproval();
        $admin = $ctx['admin'];
        $rental = $ctx['rental'];

        $this->actingAs($admin)->post(route('admin.finance.rentals.approve-payment', $rental))->assertRedirect();

        $expectedExternal = 'ledger:rental:'.$rental->id;

        $this->assertDatabaseHas('transactions', [
            'rental_id' => $rental->id,
            'amount' => '1500.00',
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'external_provider_id' => $expectedExternal,
        ]);

        $rental->refresh();
        $this->assertNotNull($rental->payment_approved_at);
        $this->assertSame('paid', strtolower((string) $rental->payment_status));
    }

    public function test_ledger_sync_is_idempotent(): void
    {
        $ctx = $this->seedRentalEligibleForPaymentApproval();
        $rental = $ctx['rental'];

        $ledger = app(RentalLedgerTransactionService::class);
        $rental->payment_approved_at = now();
        $rental->payment_status = 'paid';
        $rental->save();

        $this->assertTrue($ledger->syncLedgerTransactionForRental($rental));
        $this->assertFalse($ledger->syncLedgerTransactionForRental($rental->fresh()));

        $this->assertSame(1, Transaction::query()->where('rental_id', $rental->id)->count());
    }

    public function test_artisan_backfill_creates_missing_ledger_row(): void
    {
        $ctx = $this->seedRentalEligibleForPaymentApproval();
        $rental = $ctx['rental'];
        $rental->payment_approved_at = now()->subDay();
        $rental->payment_status = 'paid';
        $rental->save();

        $this->assertSame(0, Transaction::query()->where('rental_id', $rental->id)->count());

        $this->artisan('finance:sync-ledger-transactions')->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'rental_id' => $rental->id,
            'external_provider_id' => 'ledger:rental:'.$rental->id,
        ]);
    }

    public function test_no_synthetic_when_successful_psp_transaction_exists(): void
    {
        $ctx = $this->seedRentalEligibleForPaymentApproval();
        $rental = $ctx['rental'];

        Transaction::query()->create([
            'rental_id' => $rental->id,
            'amount' => 1500,
            'currency' => 'USD',
            'status' => 'succeeded',
            'external_provider_id' => 'stripe:pi_test_1',
            'transaction_date' => now(),
            'payment_method' => 'card',
        ]);

        $rental->payment_approved_at = now();
        $rental->payment_status = 'paid';
        $rental->save();

        $ledger = app(RentalLedgerTransactionService::class);
        $this->assertFalse($ledger->syncLedgerTransactionForRental($rental->fresh()));

        $this->assertSame(1, Transaction::query()->where('rental_id', $rental->id)->count());
    }
}
