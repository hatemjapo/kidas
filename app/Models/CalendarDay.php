<?php

namespace App\Models;

use App\Enums\BookingStatus;
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

    public static function getBookableDayForDate(CarbonImmutable $date): ?self
    {
        return self::query()
            ->where('is_released', true)
            ->where('is_closed', false)
            ->whereDate('date', $date->toDateString())
            ->first();
    }

    /**
     * @return list<int>
     */
    public function getActiveDockIdsForShiftAndUsage(Shift $shift, DockUsage $usage): array
    {
        return $this->dockUsages()
            ->where('shift', $shift)
            ->where('usage', $usage)
            ->whereHas('dock', fn (Builder $query) => $query->where('is_active', true))
            ->pluck('dock_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, list<Booking>>
     */
    public function getTimelineBookingsByDock(): array
    {
        [$dayStart, $dayEnd] = $this->getOpeningWindow();

        $bookings = Booking::query()
            ->whereIn('status', BookingStatus::onTheTimeline())
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->with('supplier')
            ->orderBy('starts_at')
            ->get();

        $byDock = [];

        foreach ($bookings as $booking) {
            $byDock[(int) $booking->dock_id][] = $booking;
        }

        return $byDock;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function getUsageByDock(): array
    {
        $usage = [];

        foreach ($this->dockUsages as $row) {
            $usage[(int) $row->dock_id][$row->shift->value] = $row->usage->value;
        }

        return $usage;
    }

    /**
     * @return array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>
     */
    public function getBlockingIntervalsByDock(): array
    {
        [$dayStart, $dayEnd] = $this->getOpeningWindow();

        $intervals = [];

        $bookings = Booking::query()
            ->whereIn('status', BookingStatus::blocking())
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['dock_id', 'starts_at', 'ends_at']);

        foreach ($bookings as $booking) {
            $intervals[(int) $booking->dock_id][] = [
                CarbonImmutable::parse($booking->starts_at),
                CarbonImmutable::parse($booking->ends_at),
            ];
        }

        return $intervals;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function getOpeningWindow(): array
    {
        return [
            $this->toInstant($this->opens_at),
            $this->toInstant($this->closes_at),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function getWindowForShift(Shift $shift): array
    {
        [$opensAt, $closesAt] = $this->getOpeningWindow();

        $startsAt = $this->toInstant($shift === Shift::Early ? $this->early_start : $this->late_start);
        $endsAt = $this->toInstant($shift === Shift::Early ? $this->early_end : $this->late_end);

        return [
            $opensAt->greaterThan($startsAt) ? $opensAt : $startsAt,
            $closesAt->lessThan($endsAt) ? $closesAt : $endsAt,
        ];
    }

    private function toInstant(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(
            "{$this->date->toDateString()} {$time}",
            (string) config('booking.timezone'),
        )->utc();
    }
}
