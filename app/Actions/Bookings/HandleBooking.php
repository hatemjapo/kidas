<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class HandleBooking
{
    public function __construct(private readonly LogBookingEvent $logBookingEvent) {}

    public function startHandlingForBooking(User $user, Booking $booking): Booking
    {
        $this->denyAnImpossibleStep($booking, BookingStatus::InProgress);

        if ($booking->hasAnUnverifiedPlateMismatch()) {
            throw new UnprocessableEntityHttpException(
                'The plate has to be confirmed with the company before handling starts.'
            );
        }

        return $this->moveBookingTo($user, $booking, BookingStatus::InProgress, 'Handling started');
    }

    public function completeHandlingForBooking(User $user, Booking $booking): Booking
    {
        $this->denyAnImpossibleStep($booking, BookingStatus::Completed);

        return $this->moveBookingTo($user, $booking, BookingStatus::Completed, 'Dock released');
    }

    private function denyAnImpossibleStep(Booking $booking, BookingStatus $next): void
    {
        if (! $booking->status->canTransitionTo($next)) {
            throw new UnprocessableEntityHttpException(
                "A booking that is {$booking->status->label()} cannot become {$next->label()}."
            );
        }
    }

    private function moveBookingTo(User $user, Booking $booking, BookingStatus $next, string $note): Booking
    {
        return DB::transaction(function () use ($user, $booking, $next, $note): Booking {
            $from = $booking->status;

            $booking->status = $next;

            if ($next === BookingStatus::InProgress) {
                $booking->started_at = now();
            }

            if ($next === BookingStatus::Completed) {
                $booking->completed_at = now();
            }

            $booking->save();

            $this->logBookingEvent->logEventForBooking($booking, $user, $from, $next, $note);

            return $booking;
        });
    }
}
