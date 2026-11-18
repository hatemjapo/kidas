<?php

namespace App\Models;

use App\Enums\DockUsage;
use App\Enums\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\LazyCollection;

#[Fillable(['is_closed', 'opens_at', 'closes_at', 'early_start', 'early_end', 'late_start', 'late_end'])]
class CalendarDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_released' => 'boolean',
            'is_closed' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DockDayUsage, $this>
     */
    public function dockUsages(): HasMany
    {
        return $this->hasMany(DockDayUsage::class);
    }

    /**
     * @return LazyCollection<int, self>
     */
    public static function getBookableDaysFrom(CarbonImmutable $from, ?CarbonImmutable $after = null): LazyCollection
    {
        $query = self::bookableFrom($from)->orderBy('date');

        if ($after instanceof CarbonImmutable) {
            $query->whereDate('date', '>', $after->toDateString());
        }

        return $query->lazy();
    }

    /**
     * @return LazyCollection<int, self>
     */
    public static function getBookableDaysBefore(CarbonImmutable $from, CarbonImmutable $before): LazyCollection
    {
        return self::bookableFrom($from)
            ->whereDate('date', '<', $before->toDateString())
            ->orderByDesc('date')
            ->lazy();
    }

    /**
     * @return Builder<self>
     */
    private static function bookableFrom(CarbonImmutable $from): Builder
    {
        return self::query()
            ->where('is_released', true)
            ->where('is_closed', false)
            ->whereDate('date', '>=', $from->setTimezone((string) config('booking.timezone'))->toDateString());
    }

    public function hasActiveDockForShiftAndUsage(Shift $shift, DockUsage $usage): bool
    {
        return $this->dockUsages()
            ->where('shift', $shift)
            ->where('usage', $usage)
            ->whereHas('dock', fn (Builder $query) => $query->where('is_active', true))
            ->exists();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function getWindowForShift(Shift $shift): array
    {
        $timezone = (string) config('booking.timezone');
        $date = $this->date->toDateString();

        $shiftStart = $shift === Shift::Early ? $this->early_start : $this->late_start;
        $shiftEnd = $shift === Shift::Early ? $this->early_end : $this->late_end;

        $opensAt = CarbonImmutable::parse("{$date} {$this->opens_at}", $timezone);
        $closesAt = CarbonImmutable::parse("{$date} {$this->closes_at}", $timezone);
        $startsAt = CarbonImmutable::parse("{$date} {$shiftStart}", $timezone);
        $endsAt = CarbonImmutable::parse("{$date} {$shiftEnd}", $timezone);

        return [
            ($opensAt->greaterThan($startsAt) ? $opensAt : $startsAt)->utc(),
            ($closesAt->lessThan($endsAt) ? $closesAt : $endsAt)->utc(),
        ];
    }
}
