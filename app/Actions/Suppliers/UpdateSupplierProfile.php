<?php

namespace App\Actions\Suppliers;

use App\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateSupplierProfile
{
    public function __invoke(
        User $user,
        string $name,
        string $companyName,
        string $companyPhone,
        ?string $driverPhone = null,
    ): Supplier {
        $supplier = $user->supplier;

        if (! $supplier instanceof Supplier) {
            throw new DomainException('This user has no supplier profile.');
        }

        return DB::transaction(function () use ($user, $supplier, $name, $companyName, $companyPhone, $driverPhone): Supplier {
            $user->name = $name;
            $user->save();

            $supplier->company_name = $companyName;
            $supplier->company_phone = $companyPhone;
            $supplier->driver_phone = $driverPhone;
            $supplier->save();

            return $supplier;
        });
    }
}
