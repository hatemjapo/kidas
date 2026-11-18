<?php

namespace App\Actions\Bookings;

use App\Data\BookableDay;
use App\Data\Slot;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\User;
use App\Support\SlotGrid;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;

class FindBookableDays
{
    public function __construct(
        private readonly DurationForPallets $durationForPallets,
        private readonly SlotGrid $slotGrid,
        private readonly LeadTime $leadTime,
    ) {}

    /**
     * @return list<BookableDay>
     */
    public function getNextBookableDaysForUser(User $user, DockUsage $usage, int $pallets, ?CarbonInterface $after = null): array
    {
        $notBefore = $this->leadTime->getEarliestStartForUser($user);

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
    public function getPreviousBookableDaysForUser(User $user, DockUsage $usage, int $pallets, CarbonInterface $before): array
    {
        $notBefore = $this->leadTime->getEarliestStartForUser($user);

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

    /**
     * @param  list<int>  $dockIds
     * @param  array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>  $intervalsByDock
     */
    private function hasFreeDock(array $dockIds, array $intervalsByDock, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        foreach ($dockIds as $dockId) {
            $taken = false;

            foreach ($intervalsByDock[$dockId] ?? [] as [$bookedFrom, $bookedUntil]) {
                if ($bookedFrom->lessThan($endsAt) && $bookedUntil->greaterThan($startsAt)) {
                    $taken = true;

                    break;
                }
            }

            if (! $taken) {
                return true;
            }
        }

        return false;
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
        $intervalsByDock = $day->getBlockingIntervalsByDock();

        foreach (Shift::cases() as $shift) {
            $dockIds = $day->getActiveDockIdsForShiftAndUsage($shift, $usage);

            if ($dockIds === []) {
                continue;
            }

            [$windowStart, $windowEnd] = $day->getWindowForShift($shift);

            foreach ($this->slotGrid->getStartTimesInWindow($windowStart, $windowEnd, $durationMinutes, $notBefore) as $startsAt) {
                $endsAt = $startsAt->addMinutes($durationMinutes);

                if (! $this->hasFreeDock($dockIds, $intervalsByDock, $startsAt, $endsAt)) {
                    continue;
                }

                $slots[] = new Slot($startsAt, $endsAt, $shift);
            }
        }

        usort($slots, fn (Slot $first, Slot $second): int => $first->startsAt <=> $second->startsAt);

        return $slots;
    }
}
