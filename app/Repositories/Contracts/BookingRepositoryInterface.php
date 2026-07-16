<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use App\Models\Trip;

interface BookingRepositoryInterface
{
    public function findTripForUpdate(int $tripId): ?Trip;

    public function create(array $data): Booking;

    public function decrementTripSeats(Trip $trip, int $seatsCount): void;
}