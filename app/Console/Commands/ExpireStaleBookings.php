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

                // تأكيد إضافي بعد القفل - المستخدم ممكن يكون دفع فعلاً
                // في اللحظة اللي بين الـ query الأول والقفل
                if ($fresh->status !== 'pending') {
                    return;
                }

                $stateMachine->transitionTo($fresh, 'expired');

                // هنا كمان تقدر ترجع المقاعد لـ "متاحة" تاني
                // $fresh->trip->seats()->increment('available_count', $fresh->seats_count);
            });
        }

        $this->info("Expired {$staleBookings->count()} stale booking(s).");
    }
}