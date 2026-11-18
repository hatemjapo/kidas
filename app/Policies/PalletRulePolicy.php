<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PalletRule;
use App\Models\User;

class PalletRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, PalletRule $palletRule): bool
    {
        return $user->role === UserRole::Admin;
    }
}
