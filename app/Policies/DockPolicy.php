<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Dock;
use App\Models\User;

class DockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Dock $dock): bool
    {
        return $user->role === UserRole::Admin;
    }
}
