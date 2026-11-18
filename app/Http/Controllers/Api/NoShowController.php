<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\MarkNoShow;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleBookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;

class NoShowController extends Controller
{
    public function store(Request $request, int $booking, MarkNoShow $markNoShow): ScheduleBookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $marked = $markNoShow->markNoShowForBooking($user, Booking::getBookingForWarehouse($booking));

        return new ScheduleBookingResource($marked);
    }
}
