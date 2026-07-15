<?php

namespace App\Repositories;

use App\Models\Trip;
use App\Repositories\Contracts\TripRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TripRepository implements TripRepositoryInterface
{
    public function search(array $filters): Collection
    {
        $query = Trip::with('train')
            ->where('status', 'scheduled')
            ->where('available_seats', '>', 0);

        if (! empty($filters['date'])) {
            $query->whereDate('trip_date', $filters['date']);
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->whereHas('train', function ($q) use ($filters) {
                if (! empty($filters['from'])) {
                    $q->where('from_station', $filters['from']);
                }
                if (! empty($filters['to'])) {
                    $q->where('to_station', $filters['to']);
                }
            });
        }

        return $query->orderBy('trip_date')->get();
    }

    public function findById(int $id): ?Trip
    {
        return Trip::with('train')->find($id);
    }
}