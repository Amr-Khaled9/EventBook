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

        $payment = $this->paymentRepository->findByReference((string) $orderId);

        if ($payment) {
            $this->paymentRepository->updateStatus(
                $payment,
                $success ? 'paid' : 'failed'
            );

            if ($success) {
                $payment->booking->update(['status' => 'confirmed']);
            }
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