<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShiftDefault;
use App\Models\User;

class ShiftDefaultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, ShiftDefault $shiftDefault): bool
    {
        return $user->role === UserRole::Admin;
    }
}
