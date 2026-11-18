<?php

namespace App\Actions\Bookings;

use App\Actions\Suppliers\ManageVehicles;
use App\Data\NewBooking;
use App\Data\Slot;
use App\Enums\BookingStatus;
use App\Enums\Shift;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SlotGrid;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateBooking
{
    public function __construct(
        private readonly DurationForPallets $durationForPallets,
        private readonly SlotGrid $slotGrid,
        private readonly ManageVehicles $manageVehicles,
    ) {}

    public function createBookingForSupplier(User $user, Supplier $supplier, NewBooking $wanted): Booking
    {
        $day = $this->getBookableDay($wanted->startsAt);
        $slot = $this->getSlotForStart($day, $wanted);

        $this->rememberVehicle($supplier, $wanted->licensePlate);

        return DB::transaction(fn (): Booking => $this->saveBooking($user, $supplier, $wanted, $day, $slot));
    }

    private function saveBooking(User $user, Supplier $supplier, NewBooking $wanted, CalendarDay $day, Slot $slot): Booking
    {
        $dock = $this->getFreeDock($day, $slot, $wanted);
        $booking = $this->storeBooking($user, $supplier, $wanted, $slot, $dock);

        $this->logTheFirstEvent($booking, $user);

        return $booking;
    }

    private function getBookableDay(CarbonImmutable $startsAt): CalendarDay
    {
        $date = $startsAt->setTimezone((string) config('booking.timezone'))->startOfDay();
        $day = CalendarDay::getBookableDayForDate($date);

        if (! $day instanceof CalendarDay) {
            throw ValidationException::withMessages([
                'starts_at' => 'This day is not open for booking.',
            ]);
        }

        return $day;
    }

    private function getSlotForStart(CalendarDay $day, NewBooking $wanted): Slot
    {
        $durationMinutes = $this->durationForPallets->getMinutesForPallets($wanted->pallets);
        $notBefore = CarbonImmutable::now()->addHours((int) config('booking.lead_time_hours'));

        foreach (Shift::cases() as $shift) {
            if ($day->getActiveDockIdsForShiftAndUsage($shift, $wanted->usage) === []) {
                continue;
            }

            [$windowStart, $windowEnd] = $day->getWindowForShift($shift);

            foreach ($this->slotGrid->getStartTimesInWindow($windowStart, $windowEnd, $durationMinutes, $notBefore) as $candidate) {
                if ($candidate->equalTo($wanted->startsAt)) {
                    return new Slot($candidate, $candidate->addMinutes($durationMinutes), $shift);
                }
            }
        }

        throw ValidationException::withMessages([
            'starts_at' => 'This is not a start time that can be booked.',
        ]);
    }

    private function getFreeDock(CalendarDay $day, Slot $slot, NewBooking $wanted): Dock
    {
        $dock = Dock::getFreeDocksForSlot(
            $day,
            $slot->shift,
            $wanted->usage,
            $slot->startsAt,
            $slot->endsAt,
            lockForUpdate: true,
        )->first();

        if (! $dock instanceof Dock) {
            throw $this->slotIsGone();
        }

        return $dock;
    }

    private function storeBooking(User $user, Supplier $supplier, NewBooking $wanted, Slot $slot, Dock $dock): Booking
    {
        $booking = new Booking;
        $booking->type = $wanted->usage;
        $booking->dock_id = $dock->id;
        $booking->supplier_id = $supplier->id;
        $booking->order_number = $wanted->orderNumber;
        $booking->pallets = $wanted->pallets;
        $booking->license_plate = $wanted->licensePlate;
        $booking->driver_phone = $wanted->driverPhone;
        $booking->starts_at = Carbon::instance($slot->startsAt->toDateTime());
        $booking->ends_at = Carbon::instance($slot->endsAt->toDateTime());
        $booking->status = BookingStatus::Booked;
        $booking->created_by = $user->id;

        try {
            $booking->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'bookings_no_overlap')) {
                throw $this->slotIsGone();
            }

            throw $exception;
        }

        return $booking;
    }

    private function logTheFirstEvent(Booking $booking, User $user): void
    {
        $event = new BookingEvent;
        $event->booking_id = $booking->id;
        $event->user_id = $user->id;
        $event->to_status = BookingStatus::Booked;
        $event->created_at = now();
        $event->save();
    }

    private function rememberVehicle(Supplier $supplier, string $licensePlate): void
    {
        if (! $supplier->hasVehicleWithPlate($licensePlate)) {
            $this->manageVehicles->addVehicle($supplier, $licensePlate);
        }
    }

    private function slotIsGone(): ConflictHttpException
    {
        return new ConflictHttpException('This slot has just been taken. Please pick another one.');
    }
}
