<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireStaleBookings extends Command
{
    protected $signature = 'bookings:expire-stale';
    protected $description = 'Expire pending bookings whose reservation window has passed';

    public function handle(BookingStateMachine $stateMachine): void
    {
        $staleBookings = Booking::where('status', 'pending')
            ->where('reservation_expires_at', '<', now())
            ->get();

        foreach ($staleBookings as $booking) {
            DB::transaction(function () use ($booking, $stateMachine) {
                $fresh = Booking::where('id', $booking->id)
                    ->lockForUpdate()
                    ->first();

                if ($fresh->status !== 'pending') {
                    return;
                }
                if ($fresh->payment !== null) {
                    return;
                }
                $stateMachine->transitionTo($fresh, 'expired');

                $fresh->trip->increment('available_seats', $fresh->seats_count);
            });
        }

        $this->info("Expired {$staleBookings->count()} stale booking(s).");
    }
}
