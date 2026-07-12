<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        Booking::whereDoesntHave('payment')->get()->each(function ($booking) {
            Payment::factory()->create(['booking_id' => $booking->id]);
        });
    }
}
