<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'provider' => 'paymob',
            'provider_reference' => fake()->uuid(),
            'amount' => fake()->randomFloat(2, 100, 1500),
            'status' => fake()->randomElement(['pending', 'paid', 'failed']),
        ];
    }
}
