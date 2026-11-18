<?php

namespace App\Actions\Suppliers;

use App\Models\Supplier;
use App\Models\Vehicle;

class ManageVehicles
{
    public function addVehicle(Supplier $supplier, string $licensePlate): Vehicle
    {
        return $supplier->vehicles()->create(['license_plate' => $licensePlate]);
    }

    public function removeVehicle(Supplier $supplier, int $vehicleId): void
    {
        $supplier->vehicles()->findOrFail($vehicleId)->delete();
    }
}
