<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function findByBookingId(int $bookingId): ?Payment;

    public function findByReference(string $reference): ?Payment;

    public function updateStatus(Payment $payment, string $status): void;
}