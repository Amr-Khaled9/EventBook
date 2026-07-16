<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogBookingActivity implements ShouldQueue
{
    public function __construct(
        protected ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function handle(BookingCreated $event): void
    {
        $this->activityLogRepository->log(
            $event->booking->user_id,
            'booking_created',
            "Booking #{$event->booking->id} created for trip #{$event->booking->trip_id}"
        );
    }
}