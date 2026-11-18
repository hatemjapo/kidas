<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\HandleBooking;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleBookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;

class HandlingController extends Controller
{
    public function store(Request $request, int $booking, HandleBooking $handleBooking): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $started = $handleBooking->startHandlingForBooking($user, Booking::getBookingForWarehouse($booking));

        return new ScheduleBookingResource($started);
    }

    public function complete(Request $request, int $booking, HandleBooking $handleBooking): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $completed = $handleBooking->completeHandlingForBooking($user, Booking::getBookingForWarehouse($booking));

        return new ScheduleBookingResource($completed);
    }
}
