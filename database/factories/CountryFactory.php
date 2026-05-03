<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso_code' => strtoupper(fake()->unique()->lexify('??')),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'interest_tax' => fake()->randomFloat(2, 0, 25),
        ];
    }
}
