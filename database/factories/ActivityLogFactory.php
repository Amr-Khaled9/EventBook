<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'action' => fake()->randomElement([
                'booking_created',
                'booking_cancelled',
                'payment_completed',
                'login',
            ]),
            'description' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
