<?php

namespace App\Services;

class PaymobWebhookVerifier
{
    public function verify(array $payload, string $receivedHmac): bool
    {
        $hmacSecret = config('services.paymob.hmac');

        $data = $payload['obj'] ?? $payload;

        $concatenatedString =
            ($data['amount_cents'] ?? '') .
            ($data['created_at'] ?? '') .
            ($data['currency'] ?? '') .
            ($data['error_occured'] ?? '') .
            ($data['has_parent_transaction'] ?? '') .
            ($data['id'] ?? '') .
            ($data['integration_id'] ?? '') .
            ($data['is_3d_secure'] ?? '') .
            ($data['is_auth'] ?? '') .
            ($data['is_capture'] ?? '') .
            ($data['is_refunded'] ?? '') .
            ($data['is_standalone_payment'] ?? '') .
            ($data['is_voided'] ?? '') .
            ($data['order']['id'] ?? '') .
            ($data['owner'] ?? '') .
            ($data['pending'] ?? '') .
            ($data['source_data']['pan'] ?? '') .
            ($data['source_data']['sub_type'] ?? '') .
            ($data['source_data']['type'] ?? '') .
            ($data['success'] ?? '');

        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}