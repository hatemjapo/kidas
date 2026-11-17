<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OpeningDefault;
use App\Models\User;

class OpeningDefaultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, OpeningDefault $openingDefault): bool
    {
        return $user->role === UserRole::Admin;
    }
}
