<?php

namespace App\Services;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function createBooking(array $data): Booking
    {
        try {
            return DB::transaction(function () use ($data) {
                $trip = $this->bookingRepository->findTripForUpdate($data['trip_id']);

                if (! $trip) {
                    throw ValidationException::withMessages([
                        'trip_id' => ['Trip not found.'],
                    ]);
                }

                if ($trip->available_seats < $data['seats_count']) {
                    throw ValidationException::withMessages([
                        'seats_count' => ['Not enough available seats for this trip.'],
                    ]);
                }

                $this->bookingRepository->decrementTripSeats($trip, $data['seats_count']);

                $booking = $this->bookingRepository->create([
                    'user_id' => Auth::id(),
                    'trip_id' => $trip->id,
                    'seats_count' => $data['seats_count'],
                    'status' => 'confirmed',
                ]);

                event(new BookingCreated($booking));

                return $booking;
            });
        } catch (ValidationException $e) {
            $this->activityLogRepository->log(
                Auth::id(),
                'booking_failed',
                'Booking failed: ' . collect($e->errors())->flatten()->first() . ' on trip id: ' . $data['trip_id']
            );

            throw $e;
        }
    }
}
