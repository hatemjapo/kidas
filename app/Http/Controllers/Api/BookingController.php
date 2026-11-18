<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Data\NewBooking;
use App\Enums\DockUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelBookingRequest;
use App\Http\Requests\Api\RescheduleBookingRequest;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(
            $this->supplierOf($request)->getBookingsPage((int) config('booking.bookings_per_page'))
        );
    }

    public function show(Request $request, int $booking): BookingResource
    {
        return new BookingResource($this->supplierOf($request)->getBookingWithHistory($booking));
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = $user->supplier;
        assert($supplier instanceof Supplier);

        $booking = $createBooking->createBookingForSupplier($user, $supplier, new NewBooking(
            usage: DockUsage::from($request->string('type')->toString()),
            pallets: $request->integer('pallets'),
            startsAt: CarbonImmutable::parse($request->string('starts_at')->toString())->utc(),
            orderNumber: $request->string('order_number')->toString(),
            licensePlate: $this->getLicensePlate($request, $supplier),
            driverPhone: $request->string('driver_phone')->toString(),
        ));

        return (new BookingResource($booking->load('dock')))->response()->setStatusCode(201);
    }

    public function update(RescheduleBookingRequest $request, int $booking, RescheduleBooking $rescheduleBooking): BookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $found = $this->supplierOf($request)->getBookingWithHistory($booking);

        $startsAt = $request->date('starts_at');

        $moved = $rescheduleBooking->rescheduleBookingForSupplier(
            $user,
            $found,
            $startsAt === null ? null : CarbonImmutable::parse($startsAt)->utc(),
            $request->input('pallets') === null ? null : $request->integer('pallets'),
        );

        return new BookingResource($moved->load('dock'));
    }

    public function cancel(CancelBookingRequest $request, int $booking, CancelBooking $cancelBooking): BookingResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $cancelled = $cancelBooking->cancelBookingForSupplier(
            $user,
            $this->supplierOf($request)->getBookingWithHistory($booking),
            $request->input('reason') === null ? null : $request->string('reason')->toString(),
        );

        return new BookingResource($cancelled->load('dock'));
    }

    private function supplierOf(Request $request): Supplier
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = $user->supplier;
        assert($supplier instanceof Supplier);

        return $supplier;
    }

    private function getLicensePlate(StoreBookingRequest $request, Supplier $supplier): string
    {
        $vehicleId = $request->integer('vehicle_id');

        if ($vehicleId > 0) {
            return $supplier->vehicles()->findOrFail($vehicleId)->license_plate;
        }

        return $request->string('license_plate')->toString();
    }
}
