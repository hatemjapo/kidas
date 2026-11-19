<?php

namespace App\Actions\Bookings;

use App\Data\Slot;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RescheduleBooking
{
    public function __construct(
        private readonly DurationForPallets $durationForPallets,
        private readonly PlanSlot $planSlot,
        private readonly LeadTime $leadTime,
        private readonly LogBookingEvent $logBookingEvent,
        private readonly BookingChangeGuard $bookingChangeGuard,
    ) {}

    public function rescheduleBookingForSupplier(
        User $user,
        Booking $booking,
        ?CarbonImmutable $startsAt,
        ?int $pallets,
    ): Booking {
        $this->bookingChangeGuard->denyAChangeThatComesTooLate($booking, 'changed');

        return $this->planTheChange($user, $booking, $startsAt, $pallets, null);
    }

    public function rescheduleBookingForAdmin(
        User $user,
        Booking $booking,
        ?CarbonImmutable $startsAt,
        ?int $pallets,
        ?string $reason,
    ): Booking {
        $this->bookingChangeGuard->denyAChangeToABookingThatIsNoLongerOpen($booking, 'changed');

        return $this->planTheChange($user, $booking, $startsAt, $pallets, $reason);
    }

    private function planTheChange(
        User $user,
        Booking $booking,
        ?CarbonImmutable $startsAt,
        ?int $pallets,
        ?string $reason,
    ): Booking {
        $wantedStart = $startsAt ?? $this->getStartOf($booking);
        $wantedPallets = $pallets ?? $booking->pallets;
        $movedFrom = $this->describe($this->getStartOf($booking));

        $day = $this->planSlot->getBookableDayForStart($wantedStart);
        $slot = $this->planSlot->getSlotForStart(
            $day,
            $booking->type,
            $wantedStart,
            $this->durationForPallets->getMinutesForPallets($wantedPallets),
            $this->leadTime->getEarliestStartForUser($user),
        );

        return DB::transaction(
            fn (): Booking => $this->moveBooking($user, $booking, $day, $slot, $wantedPallets, $movedFrom, $reason)
        );
    }

    private function moveBooking(
        User $user,
        Booking $booking,
        CalendarDay $day,
        Slot $slot,
        int $pallets,
        string $movedFrom,
        ?string $reason,
    ): Booking {
        $dock = $this->planSlot->getFreeDockForSlot($day, $slot, $booking->type, exceptBookingId: $booking->id);

        $this->storeMove($booking, $dock, $slot, $pallets);

        $this->logBookingEvent->logEventForBooking(
            $booking,
            $user,
            BookingStatus::Booked,
            BookingStatus::Booked,
            $this->getNoteForMove($movedFrom, $slot, $reason),
        );

        return $booking;
    }

    private function getNoteForMove(string $movedFrom, Slot $slot, ?string $reason): string
    {
        $note = "Moved from {$movedFrom} to {$this->describe($slot->startsAt)}";

        return $reason === null ? $note : "{$note} ({$reason})";
    }

    private function storeMove(Booking $booking, Dock $dock, Slot $slot, int $pallets): void
    {
        $booking->dock_id = $dock->id;
        $booking->pallets = $pallets;
        $booking->starts_at = Carbon::instance($slot->startsAt->toDateTime());
        $booking->ends_at = Carbon::instance($slot->endsAt->toDateTime());

        try {
            $booking->save();
        } catch (QueryException $exception) {
            if ($this->planSlot->isAnOverlapViolation($exception)) {
                throw $this->planSlot->slotIsGone();
            }

            throw $exception;
        }
    }

    private function getStartOf(Booking $booking): CarbonImmutable
    {
        return CarbonImmutable::parse($booking->starts_at);
    }

    private function describe(CarbonImmutable $moment): string
    {
        return $moment->setTimezone((string) config('booking.timezone'))->format('d.m.Y H:i');
    }
}
