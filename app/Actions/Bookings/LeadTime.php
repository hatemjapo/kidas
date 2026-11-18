<?php

namespace App\Actions\Bookings;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;

class LeadTime
{
    public function getEarliestStartForUser(User $user): CarbonImmutable
    {
        if ($user->role === UserRole::Admin) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::now()->addHours((int) config('booking.lead_time_hours'));
    }
}
