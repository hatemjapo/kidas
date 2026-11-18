<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BookingChangeGuard
{
    public function denyAChangeThatComesTooLate(Booking $booking, string $change): void
    {
        if ($booking->status !== BookingStatus::Booked) {
            throw new AccessDeniedHttpException("A booking in this state can no longer be {$change}.");
        }

        $deadline = CarbonImmutable::parse($booking->starts_at)
            ->subHours((int) config('booking.change_deadline_hours'));

        if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
            throw new AccessDeniedHttpException("A booking can only be {$change} up to three hours before its slot.");
        }
    }
}
