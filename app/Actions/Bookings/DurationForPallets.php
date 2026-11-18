<?php

namespace App\Actions\Bookings;

use App\Models\PalletRule;
use DomainException;

class DurationForPallets
{
    public function getMinutesForPallets(int $pallets): int
    {
        $maxPallets = (int) config('booking.max_pallets');

        if ($pallets < 1 || $pallets > $maxPallets) {
            throw new DomainException("A booking carries between 1 and {$maxPallets} pallets, not {$pallets}.");
        }

        $rule = PalletRule::getRuleForPallets($pallets);

        if (! $rule instanceof PalletRule) {
            throw new DomainException("No pallet rule covers {$pallets} pallets.");
        }

        return $rule->duration_minutes;
    }
}
