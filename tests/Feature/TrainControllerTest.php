<?php

namespace Tests\Feature;

use App\Models\Train;
use App\Models\User;
use App\Services\TrainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class TrainControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_trains_endpoints()
    {
        $responseIndex = $this->getJson('/api/trains');
        $responseIndex->assertStatus(401);

        $responseShow = $this->getJson('/api/trains/1');
        $responseShow->assertStatus(401);
    }

    public function test_user_can_fetch_all_trains()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TrainService::class);
        $trains = Train::factory()->count(2)->make();
        
        $mockService->shouldReceive('listAll')
            ->once()
            ->andReturn(new Collection($trains));

        $this->app->instance(TrainService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trains');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Trains fetched successfully',
            ]);
            
        // You could also assert that 'data' has 2 items, depending on exact JSON structure.
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_fetch_train_by_id()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TrainService::class);
        $train = Train::factory()->make();
        $train->id = 1;
        
        $mockService->shouldReceive('findOrFail')
            ->once()
            ->with(1)
            ->andReturn($train);

        $this->app->instance(TrainService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trains/1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Train fetched successfully',
                'data' => [
                    'id' => 1,
                ]
            ]);
    }

    public function test_train_fetch_returns_404_when_not_found()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TrainService::class);
        $exception = ValidationException::withMessages(['train' => 'Train not found.']);
        
        $mockService->shouldReceive('findOrFail')
            ->once()
            ->with(99)
            ->andThrow($exception);

        $this->app->instance(TrainService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trains/99');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'errors' => [
                    'train' => ['Train not found.']
                ]
            ]);
    }

    public function test_train_endpoints_handle_unexpected_exceptions()
    {
        $user = User::factory()->create();
        
        $mockService = Mockery::mock(TrainService::class);
        $mockService->shouldReceive('listAll')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->app->instance(TrainService::class, $mockService);

        $response = $this->actingAs($user, 'api')->getJson('/api/trains');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to fetch trains',
            ]);
    }
}
