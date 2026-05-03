<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMessage>
 */
class UserMessageFactory extends Factory
{
    protected $model = UserMessage::class;

    public function definition(): array
    {
        return [
            'recipient_user_id' => User::factory(),
            'sender_user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'read_at' => null,
        ];
    }
}
