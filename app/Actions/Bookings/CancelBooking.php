<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Mail\BookingCancelled;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CancelBooking
{
    public function __construct(
        private readonly LogBookingEvent $logBookingEvent,
        private readonly BookingChangeGuard $bookingChangeGuard,
    ) {}

    public function cancelBookingForSupplier(User $user, Booking $booking, ?string $reason): Booking
    {
        $this->bookingChangeGuard->denyAChangeThatComesTooLate($booking, 'cancelled');

        return DB::transaction(fn (): Booking => $this->storeCancellation($user, $booking, $reason));
    }

    public function cancelBookingForAdmin(User $user, Booking $booking, ?string $reason): Booking
    {
        $this->bookingChangeGuard->denyAChangeToABookingThatIsNoLongerOpen($booking, 'cancelled');

        $cancelled = DB::transaction(fn (): Booking => $this->storeCancellation($user, $booking, $reason));

        $supplierUser = $cancelled->getSupplierUser();

        if ($supplierUser instanceof User) {
            Mail::to($supplierUser)->send(new BookingCancelled($cancelled, $reason));
        }

        return $cancelled;
    }

    private function storeCancellation(User $user, Booking $booking, ?string $reason): Booking
    {
        $booking->status = BookingStatus::Cancelled;
        $booking->cancelled_at = now();
        $booking->cancel_reason = $reason;
        $booking->save();

        $this->logBookingEvent->logEventForBooking(
            $booking,
            $user,
            BookingStatus::Booked,
            BookingStatus::Cancelled,
            $reason,
        );

        return $booking;
    }
}
