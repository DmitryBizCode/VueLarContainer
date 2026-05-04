<?php

namespace Database\Factories;

use App\Models\Rental;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'USD',
            'status' => 'pending',
            'external_provider_id' => fake()->uuid(),
            'refund_reason' => null,
            'status_note' => null,
            'transaction_date' => now(),
            'payment_method' => 'card',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }
}
