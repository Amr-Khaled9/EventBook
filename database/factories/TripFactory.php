<?php

namespace Database\Factories;

use App\Models\Train;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $train = Train::inRandomOrder()->first() ?? Train::factory()->create();

        return [
            'train_id' => $train->id,
            'trip_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'available_seats' => $train->total_seats,
            'status' => 'scheduled',
        ];
    }
}
