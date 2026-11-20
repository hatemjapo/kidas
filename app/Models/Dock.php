<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\Shift;
use Carbon\CarbonImmutable;
use Database\Factories\DockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['is_active'])]
class Dock extends Model
{
    /** @use HasFactory<DockFactory> */
    use HasFactory;

    /**
     * @return Collection<int, self>
     */
    public static function getActiveDocks(): Collection
    {
        return self::query()->where('is_active', true)->orderBy('number')->get();
    }

    public static function getDockForUsage(CalendarDay $day, Shift $shift, DockUsage $usage): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->whereHas('dayUsages', fn (Builder $usages) => $usages
                ->where('calendar_day_id', $day->id)
                ->where('shift', $shift)
                ->where('usage', $usage))
            ->orderBy('number')
            ->first();
    }

    public function getBookingThatHoldsTheDockNow(CarbonImmutable $now, int $exceptBookingId): ?Booking
    {
        return $this->bookings()
            ->whereIn('status', [
                BookingStatus::Booked->value,
                BookingStatus::Arrived->value,
                BookingStatus::InProgress->value,
            ])
            ->whereKeyNot($exceptBookingId)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('ends_at')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DockDayUsage, $this>
     */
    public function dayUsages(): HasMany
    {
        return $this->hasMany(DockDayUsage::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return Collection<int, self>
     */
    public static function getFreeDocksForSlot(
        CalendarDay $day,
        Shift $shift,
        DockUsage $usage,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $lockForUpdate = false,
        ?int $exceptBookingId = null,
    ): Collection {
        $query = self::query()
            ->where('is_active', true)
            ->whereHas('dayUsages', fn (Builder $usages) => $usages
                ->where('calendar_day_id', $day->id)
                ->where('shift', $shift)
                ->where('usage', $usage))
            ->whereDoesntHave('bookings', fn (Builder $bookings) => $bookings
                ->whereIn('status', BookingStatus::blocking())
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($exceptBookingId !== null, fn (Builder $others) => $others->whereKeyNot($exceptBookingId)))
            ->orderBy('number');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }
}
