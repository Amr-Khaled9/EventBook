<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Models\Booking;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\PaymentService;
use App\Services\PaymobWebhookVerifier;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentRepositoryInterface $paymentRepository,
        protected PaymobWebhookVerifier $webhookVerifier
    ) {}

    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        try {
            $booking = Booking::findOrFail($request->validated('booking_id'));

            $result = $this->paymentService->initiatePayment(
                $booking,
                $request->validated('mobile_number')
            );

            return $this->success($result, 'Payment initiated successfully', 201);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Payment initiation failed', 500);
        }
    }

    // Server-to-server webhook — Paymob calls this directly
    public function callback(Request $request): JsonResponse
    {
        $hmac = $request->query('hmac');
        $payload = $request->all();

        if (! $this->webhookVerifier->verify($payload, $hmac)) {
            return $this->error('Invalid HMAC signature', 401);
        }

        $orderId = $payload['obj']['order']['id'] ?? null;
        $success = $payload['obj']['success'] ?? false;

        return $this->processPaymentWebhook((string) $orderId, $success);
    }

    // Simulates the provider calling us back — only usable when payment_provider = mock.
    // Lets you manually reproduce "duplicate webhook", "late confirmation", etc.
    public function mockCallback(Request $request): JsonResponse
    {
        if (config('services.payment_provider') !== 'mock') {
            return $this->error('Mock callback is disabled outside mock mode', 403);
        }

        $orderId = $request->input('provider_reference');
        $success = $request->boolean('success');

        return $this->processPaymentWebhook($orderId, $success);
    }

    private function processPaymentWebhook(string $orderId, bool $success): JsonResponse
    {
        $payment = $this->paymentRepository->findByReference($orderId);

        if (! $payment) {
            return $this->error('Payment not found', 404);
        }

        // Idempotency guard: if this payment already reached a final state,
        // a duplicate/late webhook must not re-process it (e.g. re-confirm
        // a booking or re-trigger anything tied to the status change).
        if (in_array($payment->status, ['paid', 'failed'])) {
            return $this->success(null, 'Webhook already processed, ignored');
        }

        $this->paymentRepository->updateStatus(
            $payment,
            $success ? 'paid' : 'failed'
        );

        if ($success) {
            $payment->booking->update(['status' => 'confirmed']);
        }

        return $this->success(null, 'Webhook processed');
    }

    // User-facing redirect after completing payment on their phone
    public function response(Request $request): JsonResponse
    {
        $success = $request->query('success') === 'true';

        return $this->success(
            ['success' => $success],
            $success ? 'Payment completed successfully' : 'Payment was not successful'
        );
    }
}