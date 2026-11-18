<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\User;

class LogBookingEvent
{
    public function logEventForBooking(
        Booking $booking,
        User $user,
        ?BookingStatus $fromStatus,
        BookingStatus $toStatus,
        ?string $note = null,
    ): BookingEvent {
        $event = new BookingEvent;
        $event->booking_id = $booking->id;
        $event->user_id = $user->id;
        $event->from_status = $fromStatus;
        $event->to_status = $toStatus;
        $event->note = $note;
        $event->created_at = now();
        $event->save();

        return $event;
    }
}
