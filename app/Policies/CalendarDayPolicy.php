<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CalendarDay;
use App\Models\User;

class CalendarDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, CalendarDay $calendarDay): bool
    {
        return $user->role === UserRole::Admin;
    }
}
