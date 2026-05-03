<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Port>
 */
class PortFactory extends Factory
{
    protected $model = Port::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => 'Port '.fake()->unique()->lexify('??????'),
            'city' => fake()->city(),
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
