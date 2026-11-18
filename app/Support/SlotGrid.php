<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class SlotGrid
{
    public const STEP_MINUTES = 15;

    /**
     * @return list<CarbonImmutable>
     */
    public function getStartTimesInWindow(
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        int $durationMinutes,
        CarbonImmutable $notBefore,
    ): array {
        if ($durationMinutes <= 0) {
            return [];
        }

        $startTimes = [];
        $startsAt = $windowStart;

        while ($startsAt->addMinutes($durationMinutes)->lessThanOrEqualTo($windowEnd)) {
            if ($startsAt->greaterThanOrEqualTo($notBefore)) {
                $startTimes[] = $startsAt;
            }

            $startsAt = $startsAt->addMinutes(self::STEP_MINUTES);
        }

        return $startTimes;
    }
}
