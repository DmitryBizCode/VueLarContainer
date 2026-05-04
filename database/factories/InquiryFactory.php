<?php

namespace Database\Factories;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone_number' => null,
            'telegram_username' => null,
            'subject' => fake()->sentence(6),
            'message' => fake()->paragraphs(2, true),
            'source' => 'website',
            'handling_status' => Inquiry::HANDLING_NEW,
            'admin_notes' => null,
            'converted_user_id' => null,
            'submitted_by_user_id' => null,
        ];
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['handling_status' => Inquiry::HANDLING_REJECTED]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['handling_status' => Inquiry::HANDLING_IN_PROGRESS]);
    }
}
