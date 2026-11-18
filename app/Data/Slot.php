<?php

namespace App\Data;

use App\Enums\Shift;
use Carbon\CarbonImmutable;

readonly class Slot
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public Shift $shift,
    ) {}
}
