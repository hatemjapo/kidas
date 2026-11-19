<?php

namespace App\Actions\Bookings;

use App\Data\DockReadiness;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;

class CheckDockReadiness
{
    public function getReadinessForBooking(Booking $booking): DockReadiness
    {
        if (! $this->isStillUnderway($booking)) {
            return DockReadiness::closed();
        }

        $now = CarbonImmutable::now();

        if ($now->lessThan($this->getWindowStartOf($booking))) {
            return DockReadiness::closed();
        }

        $holdingTheDock = $booking->dock->getBookingThatHoldsTheDockNow($now, $booking->id);

        return $holdingTheDock instanceof Booking
            ? DockReadiness::takenBy($holdingTheDock)
            : DockReadiness::free();
    }

    private function isStillUnderway(Booking $booking): bool
    {
        return in_array(
            $booking->status,
            [BookingStatus::Booked, BookingStatus::Arrived, BookingStatus::InProgress],
            true,
        );
    }

    private function getWindowStartOf(Booking $booking): CarbonImmutable
    {
        return CarbonImmutable::parse($booking->starts_at)
            ->subMinutes((int) config('booking.status_window_minutes'));
    }
}
