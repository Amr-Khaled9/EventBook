<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Services\BookingStateMachine;
use App\Exceptions\InvalidStateTransitionException;
use Mockery;
use Tests\TestCase;

class BookingStateMachineTest extends TestCase
{
    public function test_can_transition_from_pending_to_confirmed()
    {
        $machine = new BookingStateMachine();
        $this->assertTrue($machine->canTransition('pending', 'confirmed'));
    }

    public function test_cannot_transition_from_confirmed_to_pending()
    {
        $machine = new BookingStateMachine();
        $this->assertFalse($machine->canTransition('confirmed', 'pending'));
    }

    public function test_transition_updates_booking_status()
    {
        $machine = new BookingStateMachine();
        
        $booking = Mockery::mock(Booking::class)->makePartial();
        $booking->shouldReceive('getAttribute')->with('status')->andReturn('pending');
        $booking->shouldReceive('update')
                ->once()
                ->with(['status' => 'confirmed']);

        $machine->transitionTo($booking, 'confirmed');
        // Assertion is handled by Mockery
        $this->assertTrue(true);
    }

    public function test_transition_throws_exception_on_invalid_state()
    {
        $machine = new BookingStateMachine();
        
        $booking = Mockery::mock(Booking::class)->makePartial();
        $booking->shouldReceive('getAttribute')->with('status')->andReturn('cancelled');
        $booking->id = 1;
        
        $this->expectException(InvalidStateTransitionException::class);
        
        $machine->transitionTo($booking, 'confirmed');
    }
}
