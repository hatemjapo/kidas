<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RegisterArrival
{
    public function __construct(private readonly LogBookingEvent $logBookingEvent) {}

    public function registerArrivalForBooking(User $user, Booking $booking, string $licensePlate): Booking
    {
        if ($booking->status !== BookingStatus::Booked) {
            throw new AccessDeniedHttpException('Only a booking that is still awaited can be registered as arrived.');
        }

        $arrivedPlate = Vehicle::normalizePlate($licensePlate);
        $mismatch = $booking->license_plate !== $arrivedPlate;

        return DB::transaction(function () use ($user, $booking, $arrivedPlate, $mismatch): Booking {
            $booking->status = BookingStatus::Arrived;
            $booking->arrived_at = now();
            $booking->arrived_plate = $arrivedPlate;
            $booking->plate_mismatch = $mismatch;
            $booking->save();

            $this->logBookingEvent->logEventForBooking(
                $booking,
                $user,
                BookingStatus::Booked,
                BookingStatus::Arrived,
                $mismatch
                    ? "Arrived with {$arrivedPlate} instead of {$booking->license_plate}"
                    : "Arrived with {$arrivedPlate}",
            );

            return $booking;
        });
    }

    public function correctArrivedPlateForBooking(User $user, Booking $booking, string $licensePlate): Booking
    {
        if ($booking->status !== BookingStatus::Arrived) {
            throw new AccessDeniedHttpException('Only the plate of a lorry that is waiting can be corrected.');
        }

        $arrivedPlate = Vehicle::normalizePlate($licensePlate);

        return DB::transaction(function () use ($user, $booking, $arrivedPlate): Booking {
            $booking->arrived_plate = $arrivedPlate;
            $booking->plate_mismatch = $booking->license_plate !== $arrivedPlate;
            $booking->plate_verified_by = null;
            $booking->save();

            $this->logBookingEvent->logEventForBooking(
                $booking,
                $user,
                BookingStatus::Arrived,
                BookingStatus::Arrived,
                "Plate corrected to {$arrivedPlate}",
            );

            return $booking;
        });
    }

    public function verifyPlateForBooking(User $user, Booking $booking, ?string $note): Booking
    {
        if (! $booking->hasAnUnverifiedPlateMismatch()) {
            throw new AccessDeniedHttpException('This booking has no open plate mismatch.');
        }

        return DB::transaction(function () use ($user, $booking, $note): Booking {
            $booking->plate_verified_by = $user->id;
            $booking->save();

            $this->logBookingEvent->logEventForBooking(
                $booking,
                $user,
                BookingStatus::Arrived,
                BookingStatus::Arrived,
                $note ?? 'Plate confirmed with the company',
            );

            return $booking;
        });
    }
}
