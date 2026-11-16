<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function decide(User $user, Supplier $supplier): bool
    {
        return $user->role === UserRole::Admin;
    }
}
