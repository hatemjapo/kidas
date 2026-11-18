<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MarkNoShow
{
    public function __construct(private readonly LogBookingEvent $logBookingEvent) {}

    public function markNoShowForBooking(User $user, Booking $booking): Booking
    {
        if (! $booking->status->canTransitionTo(BookingStatus::NoShow)) {
            throw new UnprocessableEntityHttpException(
                "A booking that is {$booking->status->label()} cannot become ".BookingStatus::NoShow->label().'.'
            );
        }

        if ($booking->ends_at->isFuture()) {
            throw new UnprocessableEntityHttpException('The slot is not over yet.');
        }

        return DB::transaction(function () use ($user, $booking): Booking {
            $from = $booking->status;

            $booking->status = BookingStatus::NoShow;
            $booking->save();

            $this->logBookingEvent->logEventForBooking(
                $booking,
                $user,
                $from,
                BookingStatus::NoShow,
                'Nobody turned up',
            );

            return $booking;
        });
    }
}
