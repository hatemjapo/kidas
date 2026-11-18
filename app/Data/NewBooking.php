<?php

namespace App\Data;

use App\Enums\DockUsage;
use Carbon\CarbonImmutable;

readonly class NewBooking
{
    public function __construct(
        public DockUsage $usage,
        public int $pallets,
        public CarbonImmutable $startsAt,
        public string $orderNumber,
        public string $licensePlate,
        public string $driverPhone,
    ) {}
}
