<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class BookableDay
{
    /**
     * @param  list<Slot>  $slots
     */
    public function __construct(
        public CarbonImmutable $date,
        public array $slots,
    ) {}
}
