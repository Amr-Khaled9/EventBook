<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireStaleBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_stale_bookings()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        
        $staleBooking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
            'reservation_expires_at' => now()->subMinutes(5)
        ]);
        
        $activeBooking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
            'reservation_expires_at' => now()->addMinutes(5)
        ]);

        $this->artisan('bookings:expire-stale')
             ->assertSuccessful();

        $this->assertEquals('expired', $staleBooking->fresh()->status);
        $this->assertEquals('pending', $activeBooking->fresh()->status);
    }
}
