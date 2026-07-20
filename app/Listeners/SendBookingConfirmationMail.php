<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationMail implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId($event): string
    {
        return $event->booking->id;
    }
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        if ($booking->confirmation_email_sent_at !== null) {
            return;
        }

        Mail::to($booking->user->email)
            ->send(new BookingConfirmationMail($booking));

        $booking->update(['confirmation_email_sent_at' => now()]);
    }
}
