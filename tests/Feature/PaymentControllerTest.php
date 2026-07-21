<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PaymobWebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────
    //  Initiate Payment
    // ─────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_initiate_payment()
    {
        $response = $this->postJson('/api/payments/initiate', [
            'booking_id' => 1,
            'mobile_number' => '01012345678',
        ]);

        $response->assertStatus(401);
    }

    public function test_initiate_payment_validation_fails_without_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/initiate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id', 'mobile_number']);
    }

    public function test_initiate_payment_validation_fails_with_invalid_mobile_number()
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/initiate', [
                'booking_id' => $booking->id,
                'mobile_number' => '123456', // invalid format
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mobile_number']);
    }

    public function test_initiate_payment_success()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $mockPayment = Payment::factory()->make([
            'booking_id' => $booking->id,
            'status' => 'pending',
        ]);
        $mockPayment->id = 1;

        $mockService = Mockery::mock(PaymentService::class);
        $mockService->shouldReceive('initiatePayment')
            ->once()
            ->andReturn([
                'payment' => $mockPayment,
                'redirect_url' => 'https://example.com/pay',
            ]);

        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/initiate', [
                'booking_id' => $booking->id,
                'mobile_number' => '01012345678',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Payment initiated successfully',
            ]);
    }

    public function test_initiate_payment_fails_for_non_pending_booking()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'confirmed',
        ]);

        $mockService = Mockery::mock(PaymentService::class);
        $mockService->shouldReceive('initiatePayment')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'booking' => ["Cannot initiate payment for a booking with status [confirmed]."],
            ]));

        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/initiate', [
                'booking_id' => $booking->id,
                'mobile_number' => '01012345678',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_initiate_payment_handles_unexpected_exception()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $mockService = Mockery::mock(PaymentService::class);
        $mockService->shouldReceive('initiatePayment')
            ->once()
            ->andThrow(new \RuntimeException('Connection timed out'));

        $this->app->instance(PaymentService::class, $mockService);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/initiate', [
                'booking_id' => $booking->id,
                'mobile_number' => '01012345678',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Payment initiation failed',
            ]);
    }

    // ─────────────────────────────────────────────
    //  Paymob Callback (real webhook)
    // ─────────────────────────────────────────────

    public function test_callback_rejects_invalid_hmac()
    {
        $user = User::factory()->create();

        // Mock verifier to return false (invalid HMAC)
        $mockVerifier = Mockery::mock(PaymobWebhookVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn(false);

        $this->app->instance(PaymobWebhookVerifier::class, $mockVerifier);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/callback?hmac=invalid_hmac', [
                'obj' => [
                    'order' => ['id' => 12345],
                    'success' => true,
                ],
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid HMAC signature',
            ]);
    }

    public function test_callback_processes_successful_payment()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'provider_reference' => '99999',
            'status' => 'pending',
        ]);

        // Mock verifier to return true (valid HMAC)
        $mockVerifier = Mockery::mock(PaymobWebhookVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymobWebhookVerifier::class, $mockVerifier);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/callback?hmac=valid_hmac', [
                'obj' => [
                    'order' => ['id' => 99999],
                    'success' => true,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook processed',
            ]);

        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_callback_processes_failed_payment()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'provider_reference' => '88888',
            'status' => 'pending',
        ]);

        $mockVerifier = Mockery::mock(PaymobWebhookVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymobWebhookVerifier::class, $mockVerifier);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/callback?hmac=valid_hmac', [
                'obj' => [
                    'order' => ['id' => 88888],
                    'success' => false,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook processed',
            ]);

        $this->assertEquals('failed', $payment->fresh()->status);
        // Booking should remain pending when payment fails
        $this->assertEquals('pending', $booking->fresh()->status);
    }

    public function test_callback_idempotency_ignores_duplicate_webhook()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'confirmed',
        ]);

        // Payment already in final state (paid)
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'provider_reference' => '77777',
            'status' => 'paid',
        ]);

        $mockVerifier = Mockery::mock(PaymobWebhookVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymobWebhookVerifier::class, $mockVerifier);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/callback?hmac=valid_hmac', [
                'obj' => [
                    'order' => ['id' => 77777],
                    'success' => true,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook already processed, ignored',
            ]);

        // Nothing should change
        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_callback_returns_404_for_unknown_payment()
    {
        $user = User::factory()->create();

        $mockVerifier = Mockery::mock(PaymobWebhookVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $this->app->instance(PaymobWebhookVerifier::class, $mockVerifier);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/callback?hmac=valid_hmac', [
                'obj' => [
                    'order' => ['id' => 99999999],
                    'success' => true,
                ],
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Payment not found',
            ]);
    }

    // ─────────────────────────────────────────────
    //  Mock Callback
    // ─────────────────────────────────────────────

    public function test_mock_callback_disabled_in_production_mode()
    {
        $user = User::factory()->create();

        config(['services.payment_provider' => 'paymob']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/mock-callback', [
                'provider_reference' => 'mock_123',
                'success' => true,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Mock callback is disabled outside mock mode',
            ]);
    }

    public function test_mock_callback_works_in_mock_mode()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'provider_reference' => 'mock_order_123',
            'status' => 'pending',
        ]);

        config(['services.payment_provider' => 'mock']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/mock-callback', [
                'provider_reference' => 'mock_order_123',
                'success' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook processed',
            ]);

        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_mock_callback_handles_failed_payment()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'provider_reference' => 'mock_order_456',
            'status' => 'pending',
        ]);

        config(['services.payment_provider' => 'mock']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/payments/mock-callback', [
                'provider_reference' => 'mock_order_456',
                'success' => false,
            ]);

        $response->assertStatus(200);

        $this->assertEquals('failed', $payment->fresh()->status);
        $this->assertEquals('pending', $booking->fresh()->status);
    }

    // ─────────────────────────────────────────────
    //  Payment Response (user-facing redirect)
    // ─────────────────────────────────────────────

    public function test_response_returns_success_status()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/payments/response?success=true');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Payment completed successfully',
                'data' => ['success' => true],
            ]);
    }

    public function test_response_returns_failure_status()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/payments/response?success=false');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Payment was not successful',
                'data' => ['success' => false],
            ]);
    }
}
