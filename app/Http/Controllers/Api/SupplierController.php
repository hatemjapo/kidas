<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierOptionResource;
use App\Http\Resources\VehicleResource;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupplierOptionResource::collection(Supplier::getApprovedSuppliers());
    }

    public function vehicles(int $supplier): AnonymousResourceCollection
    {
        return VehicleResource::collection(
            Supplier::getApprovedSupplier($supplier)->vehicles()->orderBy('license_plate')->get()
        );
    }
}
