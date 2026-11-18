<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class DaySchedule
{
    /**
     * @param  list<DockSchedule>  $docks
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $isReleased,
        public CarbonImmutable $opensAt,
        public CarbonImmutable $closesAt,
        public bool $isClosed,
        public array $docks,
    ) {}
}
