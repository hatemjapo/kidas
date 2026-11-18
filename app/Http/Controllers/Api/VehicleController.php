<?php

namespace App\Http\Controllers\Api;

use App\Actions\Suppliers\ManageVehicles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return VehicleResource::collection(
            $this->supplierOf($request)->vehicles()->orderBy('license_plate')->get()
        );
    }

    public function store(StoreVehicleRequest $request, ManageVehicles $manageVehicles): JsonResponse
    {
        $vehicle = $manageVehicles->addVehicle(
            $this->supplierOf($request),
            $request->string('license_plate')->toString(),
        );

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, int $vehicle, ManageVehicles $manageVehicles): Response
    {
        $manageVehicles->removeVehicle($this->supplierOf($request), $vehicle);

        return response()->noContent();
    }

    private function supplierOf(Request $request): Supplier
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = $user->supplier;
        assert($supplier instanceof Supplier);

        return $supplier;
    }
}
