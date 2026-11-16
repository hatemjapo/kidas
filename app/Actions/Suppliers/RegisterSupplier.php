<?php

namespace App\Actions\Suppliers;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterSupplier
{
    public function __invoke(
        string $companyName,
        string $companyPhone,
        string $name,
        string $email,
        string $password,
    ): User {
        return DB::transaction(function () use ($companyName, $companyPhone, $name, $email, $password): User {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;
            $user->role = UserRole::Supplier;
            $user->save();

            $supplier = new Supplier;
            $supplier->company_name = $companyName;
            $supplier->company_phone = $companyPhone;
            $supplier->status = SupplierStatus::Pending;

            $user->supplier()->save($supplier);

            return $user;
        });
    }
}
