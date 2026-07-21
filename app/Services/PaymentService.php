<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected string $integrationId;

    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository
    ) {
        $this->baseUrl = config('services.paymob.base_url');
        $this->secretKey = config('services.paymob.secret_key');
        $this->integrationId = config('services.paymob.integration_id');
    }

    public function initiatePayment(Booking $booking, string $mobileNumber): array
    {
        // 🔑 نقفل صف الحجز، نتأكد إنه لسه pending، وننشئ سجل الـ Payment
        // كل ده جوه نفس القفل - عشان لو الـ Cron حاول ياخد نفس القفل بعدنا،
        // هيلاقي فعلياً Payment موجود ويمتنع عن الإلغاء (راجع ExpireStaleBookings)
        $payment = DB::transaction(function () use ($booking) {
            $fresh = Booking::where('id', $booking->id)->lockForUpdate()->first();

            if ($fresh->status !== 'pending') {
                throw ValidationException::withMessages([
                    'booking' => ["Cannot initiate payment for a booking with status [{$fresh->status}]."],
                ]);
            }

            $provider = config('services.payment_provider') === 'mock' ? 'mock' : 'paymob';

            return $this->paymentRepository->create([
                'booking_id' => $fresh->id,
                'provider' => $provider,
                'provider_reference' => 'pending_' . \Illuminate\Support\Str::uuid(), // placeholder، يتحدث تحت
                'amount' => $this->calculateAmount($fresh),
                'status' => 'pending',
            ]);
        });

        if (config('services.payment_provider') === 'mock') {
            return $this->initiatePaymentMock($booking, $payment);
        }

        // 1. Authentication
        $authToken = $this->authenticate();

        // 2. Order Registration
        $orderId = $this->registerOrder($authToken, $booking);

        // 3. Payment Key Request
        $paymentKey = $this->requestPaymentKey($authToken, $orderId, $booking);

        // 4. Update the Payment record (created earlier, under the lock) with the real order id
        $payment->update(['provider_reference' => (string) $orderId]);

        // 5. Pay with wallet
        $result = $this->payWithWallet($paymentKey, $mobileNumber);

        return [
            'payment' => $payment->fresh(),
            'redirect_url' => $result['redirect_url'] ?? null,
        ];
    }

    /**
     * Same shape of response as the real flow, but with no HTTP calls.
     * Lets you test the whole booking/payment lifecycle (webhook, idempotency,
     * retries...) without touching Paymob at all.
     *
     * Pass a scenario via the PAYMENT_MOCK_SCENARIO env var, or it'll be random:
     * 'success' | 'failed' | 'timeout' | 'duplicate'
     */
    protected function initiatePaymentMock(Booking $booking, Payment $payment): array
    {
        $scenario = config('services.payment_mock_scenario')
            ?? collect(['success', 'failed', 'timeout'])->random();

        if ($scenario === 'timeout') {
            Log::error('Mock payment provider timeout', ['booking_id' => $booking->id]);
            throw ValidationException::withMessages([
                'payment' => ['Payment provider timed out (simulated).'],
            ]);
        }

        $orderId = 'mock_' . \Illuminate\Support\Str::uuid();

        $payment->update(['provider_reference' => $orderId]);

        return [
            'payment' => $payment->fresh(),
            'redirect_url' => null,
            // exposed only so you can trigger the matching webhook manually in tests/Postman
            'mock_scenario' => $scenario,
        ];
    }

    protected function authenticate(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => $this->secretKey,
        ]);

        if (! $response->successful()) {
            Log::error('Paymob auth failed', ['response' => $response->body()]);
            throw ValidationException::withMessages([
                'payment' => ['Failed to authenticate with payment provider.'],
            ]);
        }

        return $response->json('token');
    }

    protected function registerOrder(string $authToken, Booking $booking): int
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $this->calculateAmount($booking) * 100,
            'currency' => 'EGP',
            'merchant_order_id' => 'booking_' . $booking->id . '_' . time(),
            'items' => [],
        ]);

        if (! $response->successful()) {
            Log::error('Paymob order registration failed', ['response' => $response->body()]);
            throw ValidationException::withMessages([
                'payment' => ['Failed to register order with payment provider.'],
            ]);
        }

        return $response->json('id');
    }

    protected function requestPaymentKey(string $authToken, int $orderId, Booking $booking): string
    {
        $user = $booking->user;

        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token' => $authToken,
            'amount_cents' => $this->calculateAmount($booking) * 100,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => [
                'apartment' => 'NA',
                'email' => $user->email,
                'floor' => 'NA',
                'first_name' => $user->name,
                'street' => 'NA',
                'building' => 'NA',
                'phone_number' => '+201000000000',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'NA',
                'country' => 'NA',
                'last_name' => 'NA',
                'state' => 'NA',
            ],
            'currency' => 'EGP',
            'integration_id' => $this->integrationId,
        ]);

        if (! $response->successful()) {
            Log::error('Paymob payment key request failed', ['response' => $response->body()]);
            throw ValidationException::withMessages([
                'payment' => ['Failed to generate payment key.'],
            ]);
        }

        return $response->json('token');
    }

    protected function payWithWallet(string $paymentKey, string $mobileNumber): array
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $mobileNumber,
                'subtype' => 'WALLET',
            ],
            'payment_token' => $paymentKey,
        ]);

        if (! $response->successful()) {
            Log::error('Paymob wallet payment failed', ['response' => $response->body()]);
            throw ValidationException::withMessages([
                'payment' => ['Failed to initiate wallet payment.'],
            ]);
        }

        Log::info("Paymob Wallet Response: ", $response->json());
        return [
            'redirect_url' => $response->json('redirect_url') ?? $response->json('iframe_redirection_url') ?? $response->json('redirection_url'),
        ];
    }

    protected function calculateAmount(Booking $booking): float
    {
        $pricePerSeat = 150;

        return $booking->seats_count * $pricePerSeat;
    }
}
