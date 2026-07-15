<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\SearchTripRequest;
use App\Services\TripService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected TripService $tripService
    ) {}

    public function index(SearchTripRequest $request): JsonResponse
    {
        try {
            $trips = $this->tripService->search($request->validated());

            return $this->success($trips, 'Trips fetched successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch trips', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $trip = $this->tripService->findOrFail($id);

            return $this->success($trip, 'Trip fetched successfully');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 404, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch trip', 500);
        }
    }
}