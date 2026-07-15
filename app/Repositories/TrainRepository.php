<?php

namespace App\Repositories;

use App\Models\Train;
use App\Repositories\Contracts\TrainRepositoryInterface;

class TrainRepository implements TrainRepositoryInterface
{
    public function findAll()
    {
        return Train::paginate(15);
    }

    public function findById(int $id): ?Train
    {
        return Train::find($id);
    }
}