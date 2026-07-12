<?php

namespace Database\Factories;

use App\Models\Train;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Train>
 */
class TrainFactory extends Factory
{
    public function definition(): array
    {
        $stations = ['Cairo', 'Alexandria', 'Luxor', 'Aswan', 'Mansoura', 'Tanta'];
        $from = fake()->randomElement($stations);
        $to = fake()->randomElement(array_diff($stations, [$from]));

        return [
            'number' => 'TRN-' . fake()->unique()->numberBetween(100, 999),
            'from_station' => $from,
            'to_station' => $to,
            'departure_time' => fake()->time('H:i:s'),
            'arrival_time' => fake()->time('H:i:s'),
            'total_seats' => fake()->numberBetween(50, 200),
        ];
    }
}
