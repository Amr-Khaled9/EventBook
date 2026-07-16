<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_booking()
    {
        $response = $this->postJson('/api/bookings', [
            'trip_id' => 1,
            'seats_count' => 2,
        ]);

        $response->assertStatus(401);
    }

    public function test_booking_validation_fails_with_invalid_data()
    {
        $user = User::factory()->create();

        // Sending empty data should fail required rules
        $response = $this->actingAs($user, 'api')->postJson('/api/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id', 'seats_count']);
    }

    public function test_user_can_create_booking_successfully()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        // Mock BookingService to isolate controller testing
        $mockService = Mockery::mock(BookingService::class);
        $mockBooking = new Booking([
            'trip_id' => $trip->id,
            'seats_count' => 2,
            'user_id' => $user->id,
        ]);
        $mockBooking->id = 1;
        
        $mockService->shouldReceive('createBooking')
            ->once()
            ->withAnyArgs()
            ->andReturn($mockBooking);

        $this->app->instance(BookingService::class, $mockService);

        $response = $this->actingAs($user, 'api')->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => 1,
                    'trip_id' => $trip->id,
                    'seats_count' => 2,
                    'user_id' => $user->id,
                ]
            ]);
    }

    public function test_booking_fails_when_service_throws_validation_exception()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        $mockService = Mockery::mock(BookingService::class);
        $exception = ValidationException::withMessages(['seats_count' => 'Not enough seats available.']);
        
        $mockService->shouldReceive('createBooking')
            ->once()
            ->andThrow($exception);

        $this->app->instance(BookingService::class, $mockService);

        $response = $this->actingAs($user, 'api')->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'errors' => [
                    'seats_count' => ['Not enough seats available.']
                ]
            ]);
    }

    public function test_booking_handles_unexpected_exceptions()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        $mockService = Mockery::mock(BookingService::class);
        $mockService->shouldReceive('createBooking')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->app->instance(BookingService::class, $mockService);

        $response = $this->actingAs($user, 'api')->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 2,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Booking failed',
            ]);
    }
}
