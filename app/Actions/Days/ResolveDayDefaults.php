<?php

namespace App\Actions\Days;

use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\OpeningDefault;
use App\Models\ShiftDefault;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ResolveDayDefaults
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(CarbonInterface $date): array
    {
        $day = CalendarDay::getDayForDate(CarbonImmutable::parse($date->toDateString()));

        $day?->loadMissing('dockUsages');

        return $day instanceof CalendarDay
            ? $this->fromDay($day)
            : $this->fromDefaults($date);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDay(CalendarDay $day): array
    {
        $usages = [];

        foreach ($this->docks() as $dock) {
            foreach (Shift::cases() as $shift) {
                $row = $day->dockUsages
                    ->first(fn (DockDayUsage $usage): bool => $usage->dock_id === $dock->id && $usage->shift === $shift);

                $usages[$dock->id][$shift->value] = $row instanceof DockDayUsage
                    ? $row->usage->value
                    : DockUsage::Inbound->value;
            }
        }

        return [
            'is_released' => $day->is_released,
            'is_closed' => $day->is_closed,
            'opens_at' => $day->opens_at,
            'closes_at' => $day->closes_at,
            'early_start' => $day->early_start,
            'early_end' => $day->early_end,
            'late_start' => $day->late_start,
            'late_end' => $day->late_end,
            'usages' => $usages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDefaults(CarbonInterface $date): array
    {
        $opening = OpeningDefault::query()->where('weekday', $date->isoWeekday())->firstOrFail();
        $early = ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail();
        $late = ShiftDefault::query()->where('shift', Shift::Late)->firstOrFail();

        $usages = [];

        foreach ($this->docks() as $dock) {
            foreach (Shift::cases() as $shift) {
                $usages[$dock->id][$shift->value] = DockUsage::Inbound->value;
            }
        }

        return [
            'is_released' => false,
            'is_closed' => $opening->is_closed,
            'opens_at' => $opening->opens_at,
            'closes_at' => $opening->closes_at,
            'early_start' => $early->starts_at,
            'early_end' => $early->ends_at,
            'late_start' => $late->starts_at,
            'late_end' => $late->ends_at,
            'usages' => $usages,
        ];
    }

    /**
     * @return Collection<int, Dock>
     */
    private function docks()
    {
        return Dock::getActiveDocks();
    }
}
