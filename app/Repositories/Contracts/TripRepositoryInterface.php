<?php

namespace App\Repositories\Contracts;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;

interface TripRepositoryInterface
{
    public function search(array $filters): Collection;

    public function findById(int $id): ?Trip;
}