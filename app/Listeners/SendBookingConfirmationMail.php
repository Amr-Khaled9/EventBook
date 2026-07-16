<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationMail implements ShouldQueue
{
    public function handle(BookingCreated $event): void
    {
        Mail::to($event->booking->user->email)
            ->send(new BookingConfirmationMail($event->booking));
    }
}