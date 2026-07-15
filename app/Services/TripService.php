<?php

namespace App\Services;

use App\Models\Trip;
use App\Repositories\Contracts\TripRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(
        protected TripRepositoryInterface $tripRepository
    ) {}

    public function search(array $filters): Collection
    {
        return $this->tripRepository->search($filters);
    }

    public function findOrFail(int $id): Trip
    {
        $trip = $this->tripRepository->findById($id);

        if (! $trip) {
            throw ValidationException::withMessages([
                'trip' => ['Trip not found.'],
            ]);
        }

        return $trip;
    }
}