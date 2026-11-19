<?php

use App\Actions\Bookings\FindBookableDays;
use App\Data\BookableDay;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

use function Pest\Laravel\travelTo;

function supplierUser(): User
{
    return User::factory()->supplier()->create();
}

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));
});

/**
 * @param  array<string, mixed>  $attributes
 */
function releaseDay(string $date, array $attributes = [], DockUsage $usage = DockUsage::Inbound): CalendarDay
{
    $day = new CalendarDay;
    $day->date = Carbon::parse($date);
    $day->is_released = true;
    $day->is_closed = false;
    $day->opens_at = '06:00:00';
    $day->closes_at = '18:00:00';
    $day->early_start = '06:00:00';
    $day->early_end = '14:00:00';
    $day->late_start = '14:00:00';
    $day->late_end = '18:00:00';

    foreach ($attributes as $column => $value) {
        $day->{$column} = $value;
    }

    $day->save();

    foreach (Dock::query()->get() as $dock) {
        foreach (Shift::cases() as $shift) {
            $row = new DockDayUsage;
            $row->calendar_day_id = $day->id;
            $row->dock_id = $dock->id;
            $row->shift = $shift;
            $row->usage = $usage;
            $row->save();
        }
    }

    return $day;
}

/**
 * @param  list<BookableDay>  $days
 * @return list<string>
 */
function dates(array $days): array
{
    return array_map(fn (BookableDay $day): string => $day->date->toDateString(), $days);
}

/**
 * @return list<string>
 */
function times(BookableDay $day): array
{
    return array_map(
        fn ($slot): string => $slot->startsAt->setTimezone('Europe/Berlin')->format('H:i'),
        $day->slots
    );
}

test('hands out the next five bookable days', function () {
    foreach (range(1, 8) as $offset) {
        releaseDay(CarbonImmutable::parse('2027-06-01')->addDays($offset)->toDateString());
    }

    $days = app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);

    expect(dates($days))->toBe(['2027-06-02', '2027-06-03', '2027-06-04', '2027-06-05', '2027-06-06']);
});

test('skips the days that are closed or not released', function () {
    releaseDay('2027-06-02');
    releaseDay('2027-06-03', ['is_closed' => true]);
    releaseDay('2027-06-04', ['is_released' => false]);
    releaseDay('2027-06-05');

    $days = app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);

    expect(dates($days))->toBe(['2027-06-02', '2027-06-05']);
});

test('leaves the past behind', function () {
    releaseDay('2027-05-30');
    releaseDay('2027-06-02');

    expect(dates(app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10)))
        ->toBe(['2027-06-02']);
});

test('pages forward without repeating a day', function () {
    foreach (range(1, 12) as $offset) {
        releaseDay(CarbonImmutable::parse('2027-06-01')->addDays($offset)->toDateString());
    }

    $finder = app(FindBookableDays::class);

    $first = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);
    $second = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10, end($first)->date);

    expect(dates($second))->toBe(['2027-06-07', '2027-06-08', '2027-06-09', '2027-06-10', '2027-06-11'])
        ->and(array_intersect(dates($first), dates($second)))->toBe([]);
});

test('pages back to the days before, in the order of the calendar', function () {
    foreach (range(1, 12) as $offset) {
        releaseDay(CarbonImmutable::parse('2027-06-01')->addDays($offset)->toDateString());
    }

    $finder = app(FindBookableDays::class);

    $first = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);
    $second = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10, end($first)->date);
    $back = $finder->getPreviousBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10, $second[0]->date);

    expect(dates($back))->toBe(dates($first));
});

test('finds nothing before the first bookable day', function () {
    releaseDay('2027-06-02');

    $days = app(FindBookableDays::class)->getPreviousBookableDaysForUser(
        supplierUser(),
        DockUsage::Inbound,
        10,
        CarbonImmutable::parse('2027-06-02'),
    );

    expect($days)->toBe([]);
});

test('ignores a day that carries the other usage only', function () {
    releaseDay('2027-06-02', usage: DockUsage::Outbound);
    releaseDay('2027-06-03', usage: DockUsage::Inbound);

    expect(dates(app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10)))
        ->toBe(['2027-06-03']);
});

test('ignores the docks that are switched off', function () {
    releaseDay('2027-06-02');

    Dock::query()->update(['is_active' => false]);

    expect(app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10))->toBe([]);
});

test('respects the opening hours over the shift times', function () {
    releaseDay('2027-06-02', ['opens_at' => '10:00:00', 'closes_at' => '12:00:00']);

    $days = app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);

    expect(times($days[0]))->toBe(['10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30', '11:45']);
});

test('never lets a slot run across the change of shift', function () {
    releaseDay('2027-06-02', [
        'opens_at' => '13:00:00',
        'closes_at' => '15:00:00',
        'early_end' => '14:00:00',
        'late_start' => '14:00:00',
    ]);

    $days = app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 20);

    expect(times($days[0]))->toBe(['13:00', '13:15', '13:30', '14:00', '14:15', '14:30']);
});

test('offers fewer start times the fuller the lorry', function () {
    releaseDay('2027-06-02');

    $finder = app(FindBookableDays::class);

    $small = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);
    $large = $finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 70);

    expect(count(times($small[0])))->toBeGreaterThan(count(times($large[0])));
});

test('drops a day on which the lorry no longer fits', function () {
    releaseDay('2027-06-02', ['opens_at' => '06:00:00', 'closes_at' => '07:00:00']);

    $finder = app(FindBookableDays::class);

    expect(dates($finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10)))->toBe(['2027-06-02'])
        ->and($finder->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 70))->toBe([]);
});

test('keeps three hours of distance from now', function () {
    travelTo(CarbonImmutable::parse('2027-06-02 08:00', 'Europe/Berlin'));

    releaseDay('2027-06-02');

    $days = app(FindBookableDays::class)->getNextBookableDaysForUser(supplierUser(), DockUsage::Inbound, 10);

    expect(times($days[0])[0])->toBe('11:00');
});
