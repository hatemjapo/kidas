<?php

namespace App\Data;

use App\Models\Booking;

readonly class DockSchedule
{
    /**
     * @param  array<string, string|null>  $usage
     * @param  list<Booking>  $bookings
     */
    public function __construct(
        public int $number,
        public bool $isActive,
        public array $usage,
        public array $bookings,
    ) {}
}
