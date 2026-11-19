<?php

namespace App\Data;

use App\Models\Booking;
use Carbon\CarbonImmutable;

readonly class DockReadiness
{
    public function __construct(
        public bool $windowOpen,
        public bool $dockFree,
        public ?CarbonImmutable $busyUntil,
    ) {}

    public static function closed(): self
    {
        return new self(false, false, null);
    }

    public static function free(): self
    {
        return new self(true, true, null);
    }

    public static function takenBy(Booking $booking): self
    {
        return new self(true, false, CarbonImmutable::parse($booking->ends_at));
    }
}
