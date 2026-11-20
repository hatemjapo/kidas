<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\RegisterArrival;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterArrivalRequest;
use App\Http\Requests\Api\VerifyPlateRequest;
use App\Http\Resources\ScheduleBookingResource;
use App\Models\Booking;
use App\Models\User;

class ArrivalController extends Controller
{
    public function store(RegisterArrivalRequest $request, int $bookingId, RegisterArrival $registerArrival): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $arrived = $registerArrival->registerArrivalForBooking(
            $user,
            Booking::getBookingForWarehouse($bookingId),
            $request->string('license_plate')->toString(),
        );

        return new ScheduleBookingResource($arrived);
    }

    public function update(RegisterArrivalRequest $request, int $bookingId, RegisterArrival $registerArrival): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $corrected = $registerArrival->correctArrivedPlateForBooking(
            $user,
            Booking::getBookingForWarehouse($bookingId),
            $request->string('license_plate')->toString(),
        );

        return new ScheduleBookingResource($corrected);
    }

    public function verify(VerifyPlateRequest $request, int $bookingId, RegisterArrival $registerArrival): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $verified = $registerArrival->verifyPlateForBooking(
            $user,
            Booking::getBookingForWarehouse($bookingId),
            $request->input('note') === null ? null : $request->string('note')->toString(),
        );

        return new ScheduleBookingResource($verified);
    }
}
