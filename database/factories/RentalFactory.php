<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    protected $model = Rental::class;

    public function definition(): array
    {
        $start = now()->addDays(3);

        return [
            'user_id' => User::factory(),
            'container_id' => fn () => Container::query()->value('id') ?? 1,
            'route_id' => null,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(14),
            'rental_days' => 14,
            'cargo_types' => ['electronics'],
            'cargo_details' => null,
            'priority' => 'normal',
            'loading_type' => 'fcl',
            'delivery_mode' => 'port_to_port',
            'sustainability_pref' => 'standard',
            'insurance_required' => false,
            'requires_customs_clearance' => false,
            'hazardous_material' => false,
            'requires_escort' => false,
            'seal_required' => false,
            'contact_name' => fake()->name(),
            'contact_phone' => '+1000555'.fake()->numerify('####'),
            'terms_accepted' => true,
            'estimated_distance' => 500.0,
            'price' => 1500.0,
            'price_breakdown' => null,
            'status' => 'pending_approval',
            'is_telemetry_active' => true,
            'payment_status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }
}
