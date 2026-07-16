<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class TripControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_trips_endpoints()
    {
        $responseIndex = $this->getJson('/api/trips');
        $responseIndex->assertStatus(401);

        $responseShow = $this->getJson('/api/trips/1');
        $responseShow->assertStatus(401);
    }

    public function test_user_can_fetch_trips_without_filters()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TripService::class);
        $trips = Trip::factory()->count(2)->make();
        
        $mockService->shouldReceive('search')
            ->once()
            ->with([])
            ->andReturn(new Collection($trips));

        $this->app->instance(TripService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trips');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Trips fetched successfully',
            ]);
            
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_fetch_trips_with_filters()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TripService::class);
        $trips = Trip::factory()->count(1)->make();
        
        $filters = ['date' => '2024-12-01'];

        $mockService->shouldReceive('search')
            ->once()
            ->with($filters)
            ->andReturn(new Collection($trips));

        $this->app->instance(TripService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trips?date=2024-12-01');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_trip_validation_fails_with_invalid_filters()
    {
        $user = User::factory()->create();
        
        // sending invalid date format for date filter
        $response = $this->actingAs($user, 'api')->getJson('/api/trips?date=not-a-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_user_can_fetch_trip_by_id()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TripService::class);
        $trip = Trip::factory()->make();
        $trip->id = 1;
        
        $mockService->shouldReceive('findOrFail')
            ->once()
            ->with(1)
            ->andReturn($trip);

        $this->app->instance(TripService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trips/1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Trip fetched successfully',
                'data' => [
                    'id' => 1,
                ]
            ]);
    }

    public function test_trip_fetch_returns_404_when_not_found()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TripService::class);
        $exception = ValidationException::withMessages(['trip' => 'Trip not found.']);
        
        $mockService->shouldReceive('findOrFail')
            ->once()
            ->with(99)
            ->andThrow($exception);

        $this->app->instance(TripService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trips/99');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'errors' => [
                    'trip' => ['Trip not found.']
                ]
            ]);
    }

    public function test_trip_endpoints_handle_unexpected_exceptions()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TripService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->app->instance(TripService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trips');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to fetch trips',
            ]);
    }
}
