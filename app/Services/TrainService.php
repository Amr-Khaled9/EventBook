<?php

namespace App\Services;

use App\Models\Train;
use App\Repositories\Contracts\TrainRepositoryInterface;
use Illuminate\Validation\ValidationException;

class TrainService
{
    public function __construct(
        protected TrainRepositoryInterface $trainRepository
    ) {}

    public function listAll()
    {
        return $this->trainRepository->findAll();
    }

    public function findOrFail(int $id): Train
    {
        $train = $this->trainRepository->findById($id);

        if (! $train) {
            throw ValidationException::withMessages([
                'train' => ['Train not found.'],
            ]);
        }

        return $train;
    }
}