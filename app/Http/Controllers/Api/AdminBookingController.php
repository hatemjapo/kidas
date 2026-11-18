<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bookings\CreateBooking;
use App\Data\NewBooking;
use App\Enums\DockUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAdminBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AdminBookingController extends Controller
{
    public function store(StoreAdminBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = Supplier::getApprovedSupplier($request->integer('supplier_id'));

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

    private function getLicensePlate(StoreAdminBookingRequest $request, Supplier $supplier): string
    {
        $vehicleId = $request->integer('vehicle_id');

        if ($vehicleId > 0) {
            return $supplier->getPlateOfVehicle($vehicleId);
        }

        return $request->string('license_plate')->toString();
    }
}
