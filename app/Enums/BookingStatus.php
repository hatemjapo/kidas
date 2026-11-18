<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Booked = 'booked';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Arrived => 'Arrived',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /**
     * The states the warehouse timeline shows: everything that still means a lorry.
     *
     * @return list<string>
     */
    public static function onTheTimeline(): array
    {
        return collect(self::cases())
            ->reject(fn (self $status): bool => $status === self::Cancelled)
            ->map(fn (self $status): string => $status->value)
            ->values()
            ->all();
    }

    /**
     * The states in which a booking still holds its dock.
     *
     * @return list<string>
     */
    public static function blocking(): array
    {
        return [
            self::Booked->value,
            self::Arrived->value,
            self::InProgress->value,
            self::Completed->value,
        ];
    }
}
