<?php

namespace Database\Seeders;

use App\Models\Train;
use App\Models\Trip;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $trains = Train::all();
        $dates = collect(range(0, 29))
            ->map(fn($day) => now()->addDays($day)->format('Y-m-d'));

        $combinations = $trains->crossJoin($dates)->shuffle()->take(30);

        foreach ($combinations as [$train, $date]) {
            Trip::factory()->create([
                'train_id' => $train->id,
                'trip_date' => $date,
                'available_seats' => $train->total_seats,
            ]);
        }
    }
}
