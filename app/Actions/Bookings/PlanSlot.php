<?php

namespace App\Actions\Bookings;

use App\Data\Slot;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Support\SlotGrid;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlanSlot
{
    public function __construct(private readonly SlotGrid $slotGrid) {}

    public function getBookableDayForStart(CarbonImmutable $startsAt): CalendarDay
    {
        $date = $startsAt->setTimezone((string) config('booking.timezone'))->startOfDay();
        $day = CalendarDay::getBookableDayForDate($date);

        if (! $day instanceof CalendarDay) {
            throw ValidationException::withMessages([
                'starts_at' => 'This day is not open for booking.',
            ]);
        }

        return $day;
    }

    public function getSlotForStart(
        CalendarDay $day,
        DockUsage $usage,
        CarbonImmutable $startsAt,
        int $durationMinutes,
        CarbonImmutable $notBefore,
    ): Slot {
        foreach (Shift::cases() as $shift) {
            if ($day->getActiveDockIdsForShiftAndUsage($shift, $usage) === []) {
                continue;
            }

            [$windowStart, $windowEnd] = $day->getWindowForShift($shift);

            foreach ($this->slotGrid->getStartTimesInWindow($windowStart, $windowEnd, $durationMinutes, $notBefore) as $candidate) {
                if ($candidate->equalTo($startsAt)) {
                    return new Slot($candidate, $candidate->addMinutes($durationMinutes), $shift);
                }
            }
        }

        throw ValidationException::withMessages([
            'starts_at' => 'This is not a start time that can be booked.',
        ]);
    }

    public function getFreeDockForSlot(CalendarDay $day, Slot $slot, DockUsage $usage, ?int $exceptBookingId = null): Dock
    {
        $dock = Dock::getFreeDocksForSlot(
            $day,
            $slot->shift,
            $usage,
            $slot->startsAt,
            $slot->endsAt,
            lockForUpdate: true,
            exceptBookingId: $exceptBookingId,
        )->first();

        if (! $dock instanceof Dock) {
            throw $this->slotIsGone();
        }

        return $dock;
    }

    public function slotIsGone(): ConflictHttpException
    {
        return new ConflictHttpException('This slot has just been taken. Please pick another one.');
    }

    public function isAnOverlapViolation(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'bookings_no_overlap');
    }
}
