<?php

namespace App\Actions\Bookings;

use App\Data\BookableDay;
use App\Data\Slot;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Support\SlotGrid;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;

class FindBookableDays
{
    public function __construct(
        private readonly DurationForPallets $durationForPallets,
        private readonly SlotGrid $slotGrid,
    ) {}

    /**
     * @return list<BookableDay>
     */
    public function getNextBookableDays(DockUsage $usage, int $pallets, ?CarbonInterface $after = null): array
    {
        $notBefore = $this->getEarliestStart();

        return $this->collectBookableDays(
            CalendarDay::getBookableDaysFrom($notBefore, $this->toImmutableDate($after)),
            $usage,
            $pallets,
            $notBefore,
        );
    }

    /**
     * @return list<BookableDay>
     */
    public function getPreviousBookableDays(DockUsage $usage, int $pallets, CarbonInterface $before): array
    {
        $notBefore = $this->getEarliestStart();

        $days = $this->collectBookableDays(
            CalendarDay::getBookableDaysBefore($notBefore, CarbonImmutable::parse($before->toDateString())),
            $usage,
            $pallets,
            $notBefore,
        );

        return array_reverse($days);
    }

    /**
     * @param  LazyCollection<int, CalendarDay>  $days
     * @return list<BookableDay>
     */
    private function collectBookableDays(LazyCollection $days, DockUsage $usage, int $pallets, CarbonImmutable $notBefore): array
    {
        $durationMinutes = $this->durationForPallets->getMinutesForPallets($pallets);
        $daysPerPage = (int) config('booking.days_per_page');

        $bookableDays = [];

        foreach ($days as $day) {
            $slots = $this->getSlotsForDay($day, $usage, $durationMinutes, $notBefore);

            if ($slots === []) {
                continue;
            }

            $bookableDays[] = new BookableDay(CarbonImmutable::parse($day->date->toDateString()), $slots);

            if (count($bookableDays) === $daysPerPage) {
                break;
            }
        }

        return $bookableDays;
    }

    private function getEarliestStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours((int) config('booking.lead_time_hours'));
    }

    private function toImmutableDate(?CarbonInterface $date): ?CarbonImmutable
    {
        return $date instanceof CarbonInterface ? CarbonImmutable::parse($date->toDateString()) : null;
    }

    /**
     * @return list<Slot>
     */
    private function getSlotsForDay(CalendarDay $day, DockUsage $usage, int $durationMinutes, CarbonImmutable $notBefore): array
    {
        $slots = [];

        foreach (Shift::cases() as $shift) {
            if (! $day->hasActiveDockForShiftAndUsage($shift, $usage)) {
                continue;
            }

            [$windowStart, $windowEnd] = $day->getWindowForShift($shift);

            foreach ($this->slotGrid->getStartTimesInWindow($windowStart, $windowEnd, $durationMinutes, $notBefore) as $startsAt) {
                $slots[] = new Slot($startsAt, $startsAt->addMinutes($durationMinutes), $shift);
            }
        }

        usort($slots, fn (Slot $first, Slot $second): int => $first->startsAt <=> $second->startsAt);

        return $slots;
    }
}
