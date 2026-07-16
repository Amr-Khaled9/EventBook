<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\Trip;
use App\Repositories\Contracts\BookingRepositoryInterface;

class BookingRepository implements BookingRepositoryInterface
{
    public function findTripForUpdate(int $tripId): ?Trip
    {
        return Trip::where('id', $tripId)->lockForUpdate()->first();
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function decrementTripSeats(Trip $trip, int $seatsCount): void
    {
        $trip->decrement('available_seats', $seatsCount);
    }
}