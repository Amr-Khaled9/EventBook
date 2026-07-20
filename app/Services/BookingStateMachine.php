<?php

namespace App\Services;

use App\Models\Booking;
use App\Exceptions\InvalidStateTransitionException;

class BookingStateMachine
{
    /**
     * Map of: current status => list of statuses it's allowed to move to.
     */
    private array $allowedTransitions = [
        'pending'   => ['confirmed', 'cancelled', 'expired'],
        'confirmed' => ['cancelled', 'completed'],
        'cancelled' => [],
        'expired'   => [],
    ];

    public function transitionTo(Booking $booking, string $newStatus): void
    {
        $current = $booking->status;

        if (! $this->canTransition($current, $newStatus)) {
            throw new InvalidStateTransitionException(
                "Cannot transition booking #{$booking->id} from [{$current}] to [{$newStatus}]"
            );
        }

        $booking->update(['status' => $newStatus]);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->allowedTransitions[$from] ?? []);
    }
}