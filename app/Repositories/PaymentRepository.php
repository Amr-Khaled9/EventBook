<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findByBookingId(int $bookingId): ?Payment
    {
        return Payment::where('booking_id', $bookingId)->first();
    }

    public function findByReference(string $reference): ?Payment
    {
        return Payment::where('provider_reference', $reference)->first();
    }

    public function updateStatus(Payment $payment, string $status): void
    {
        $payment->update(['status' => $status]);
    }
}