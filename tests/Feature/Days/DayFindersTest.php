<?php

use App\Models\CalendarDay;
use App\Models\Dock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

function releasedDay(string $date, bool $released = true): CalendarDay
{
    $day = new CalendarDay;
    $day->date = Carbon::parse($date);
    $day->is_released = $released;
    $day->is_closed = false;
    $day->opens_at = '06:00:00';
    $day->closes_at = '18:00:00';
    $day->early_start = '06:00:00';
    $day->early_end = '14:00:00';
    $day->late_start = '14:00:00';
    $day->late_end = '18:00:00';
    $day->save();

    return $day;
}

test('hands out the active docks in order', function () {
    $dock = Dock::query()->where('number', 4)->firstOrFail();
    $dock->is_active = false;
    $dock->save();

    $docks = Dock::getActiveDocks();

    expect($docks)->toHaveCount(9)
        ->and($docks->pluck('number')->all())->toBe([1, 2, 3, 5, 6, 7, 8, 9, 10]);
});

test('names the released days of one month', function () {
    releasedDay('2027-06-02');
    releasedDay('2027-06-04');
    releasedDay('2027-06-05', released: false);
    releasedDay('2027-07-01');

    expect(CalendarDay::getReleasedDatesInMonth(CarbonImmutable::parse('2027-06-15')))
        ->toBe(['2027-06-02', '2027-06-04']);
});

test('finds the day behind a date, and nothing for a day that was never touched', function () {
    releasedDay('2027-06-02');

    expect(CalendarDay::getDayForDate(CarbonImmutable::parse('2027-06-02'))?->date->toDateString())
        ->toBe('2027-06-02')
        ->and(CalendarDay::getDayForDate(CarbonImmutable::parse('2027-06-03')))->toBeNull();
});

test('keeps the queries that carry a meaning out of the panel', function () {
    $page = (string) file_get_contents(app_path('Filament/Pages/Days.php'));

    expect($page)->not->toContain('::query()')
        ->and($page)->not->toContain('->where(');
});
