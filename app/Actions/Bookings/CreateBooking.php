<?php

namespace App\Actions\Bookings;

use App\Actions\Suppliers\ManageVehicles;
use App\Data\NewBooking;
use App\Data\Slot;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateBooking
{
    public function __construct(
        private readonly DurationForPallets $durationForPallets,
        private readonly PlanSlot $planSlot,
        private readonly ManageVehicles $manageVehicles,
        private readonly LogBookingEvent $logBookingEvent,
    ) {}

    public function createBookingForSupplier(User $user, Supplier $supplier, NewBooking $wanted): Booking
    {
        $durationMinutes = $this->durationForPallets->getMinutesForPallets($wanted->pallets);

        $day = $this->planSlot->getBookableDayForStart($wanted->startsAt);
        $slot = $this->planSlot->getSlotForStart($day, $wanted->usage, $wanted->startsAt, $durationMinutes);

        $this->rememberVehicle($supplier, $wanted->licensePlate);

        return DB::transaction(fn (): Booking => $this->saveBooking($user, $supplier, $wanted, $day, $slot));
    }

    private function saveBooking(User $user, Supplier $supplier, NewBooking $wanted, CalendarDay $day, Slot $slot): Booking
    {
        $dock = $this->planSlot->getFreeDockForSlot($day, $slot, $wanted->usage);
        $booking = $this->storeBooking($user, $supplier, $wanted, $slot, $dock);

        $this->logBookingEvent->logEventForBooking($booking, $user, null, BookingStatus::Booked);

        return $booking;
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
            if ($this->planSlot->isAnOverlapViolation($exception)) {
                throw $this->planSlot->slotIsGone();
            }

            throw $exception;
        }

        return $booking;
    }

    private function rememberVehicle(Supplier $supplier, string $licensePlate): void
    {
        if (! $supplier->hasVehicleWithPlate($licensePlate)) {
            $this->manageVehicles->addVehicle($supplier, $licensePlate);
        }
    }
}
