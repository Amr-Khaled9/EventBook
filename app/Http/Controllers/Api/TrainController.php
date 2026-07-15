<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrainService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TrainController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected TrainService $trainService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $trains = $this->trainService->listAll();

            return $this->success($trains, 'Trains fetched successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch trains', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $train = $this->trainService->findOrFail($id);

            return $this->success($train, 'Train fetched successfully');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 404, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch train', 500);
        }
    }
}
