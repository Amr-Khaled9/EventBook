<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 2. مستخدمين عاديين عشوائيين
        User::factory(15)->create();

        // 3. الباقي بالترتيب المنطقي حسب الاعتمادية بين الجداول
        $this->call([
            TrainSeeder::class,
            TripSeeder::class,
            BookingSeeder::class,
            PaymentSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
