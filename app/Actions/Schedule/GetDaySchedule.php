<?php

namespace App\Actions\Schedule;

use App\Data\DaySchedule;
use App\Data\DockSchedule;
use App\Enums\Shift;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\OpeningDefault;
use Carbon\CarbonImmutable;

class GetDaySchedule
{
    public function getScheduleForDate(CarbonImmutable $date): DaySchedule
    {
        $day = CalendarDay::query()
            ->whereDate('date', $date->toDateString())
            ->with('dockUsages')
            ->first();

        return $day instanceof CalendarDay
            ? $this->fromDay($day)
            : $this->fromDefaults($date);
    }

    private function fromDay(CalendarDay $day): DaySchedule
    {
        [$opensAt, $closesAt] = $day->getOpeningWindow();

        $bookingsByDock = $day->getTimelineBookingsByDock();
        $usageByDock = $day->getUsageByDock();

        return new DaySchedule(
            CarbonImmutable::parse($day->date->toDateString()),
            $day->is_released,
            $opensAt,
            $closesAt,
            $day->is_closed,
            $this->docks(
                fn (Dock $dock): array => $usageByDock[$dock->id] ?? $this->noUsage(),
                fn (Dock $dock): array => $bookingsByDock[$dock->id] ?? [],
            ),
        );
    }

    private function fromDefaults(CarbonImmutable $date): DaySchedule
    {
        $opening = OpeningDefault::query()->where('weekday', $date->isoWeekday())->firstOrFail();
        $timezone = (string) config('booking.timezone');

        return new DaySchedule(
            $date->startOfDay(),
            false,
            CarbonImmutable::parse("{$date->toDateString()} {$opening->opens_at}", $timezone)->utc(),
            CarbonImmutable::parse("{$date->toDateString()} {$opening->closes_at}", $timezone)->utc(),
            $opening->is_closed,
            $this->docks(fn (Dock $dock): array => $this->noUsage(), fn (Dock $dock): array => []),
        );
    }

    /**
     * @param  callable(Dock): array<string, string|null>  $usageOf
     * @param  callable(Dock): list<Booking>  $bookingsOf
     * @return list<DockSchedule>
     */
    private function docks(callable $usageOf, callable $bookingsOf): array
    {
        return Dock::query()
            ->orderBy('number')
            ->get()
            ->map(fn (Dock $dock): DockSchedule => new DockSchedule(
                $dock->number,
                $dock->is_active,
                $usageOf($dock),
                $bookingsOf($dock),
            ))
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function noUsage(): array
    {
        return collect(Shift::cases())
            ->mapWithKeys(fn (Shift $shift): array => [$shift->value => null])
            ->all();
    }
}
