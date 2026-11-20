<?php

namespace App\Actions\Days;

use App\Models\CalendarDay;
use App\Models\DockDayUsage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaveDay
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function __invoke(CarbonInterface $date, array $state): CalendarDay
    {
        if ($date->isBefore(today())) {
            throw new DomainException('A day in the past cannot be changed.');
        }

        return DB::transaction(function () use ($date, $state): CalendarDay {
            $day = CalendarDay::getDayForDate(CarbonImmutable::parse($date->toDateString())) ?? new CalendarDay;

            $day->date = Carbon::parse($date->toDateString());
            $day->is_closed = (bool) ($state['is_closed'] ?? false);
            $day->opens_at = (string) $state['opens_at'];
            $day->closes_at = (string) $state['closes_at'];
            $day->early_start = (string) $state['early_start'];
            $day->early_end = (string) $state['early_end'];
            $day->late_start = (string) $state['late_start'];
            $day->late_end = (string) $state['late_end'];
            $isReleased = (bool) ($state['is_released'] ?? false);
            $day->released_at = $isReleased ? ($day->released_at ?? now()) : null;
            $day->is_released = $isReleased;

            $day->save();

            /** @var array<int, array<string, string>> $usages */
            $usages = $state['usages'] ?? [];

            $now = now();
            $rows = [];

            foreach ($usages as $dockId => $perShift) {
                foreach ($perShift as $shift => $usage) {
                    $rows[] = [
                        'calendar_day_id' => $day->id,
                        'dock_id' => (int) $dockId,
                        'shift' => $shift,
                        'usage' => $usage,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DockDayUsage::query()->upsert($rows, ['calendar_day_id', 'shift', 'dock_id'], ['usage', 'updated_at']);
            }

            return $day;
        });
    }
}
