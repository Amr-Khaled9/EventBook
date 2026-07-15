<?php

namespace App\Repositories\Contracts;

use App\Models\Train;
use Illuminate\Database\Eloquent\Collection;

interface TrainRepositoryInterface
{
    public function findAll();

    public function findById(int $id): ?Train;
}