<?php

use App\Support\SlotGrid;
use Carbon\CarbonImmutable;

function berlin(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Europe/Berlin')->utc();
}

/**
 * @return list<string>
 */
function startTimes(string $windowStart, string $windowEnd, int $durationMinutes, string $notBefore = '2027-01-01 00:00'): array
{
    return array_map(
        fn (CarbonImmutable $startsAt): string => $startsAt->setTimezone('Europe/Berlin')->format('H:i'),
        (new SlotGrid)->getStartTimesInWindow(
            berlin($windowStart),
            berlin($windowEnd),
            $durationMinutes,
            berlin($notBefore),
        )
    );
}

test('walks the window in quarter hours', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 15))
        ->toBe(['06:00', '06:15', '06:30', '06:45']);
});

test('stops early enough for the duration to fit', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 30))
        ->toBe(['06:00', '06:15', '06:30']);
});

test('offers nothing when the duration outgrows the window', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 75))->toBe([]);
});

test('offers the single slot that fills the window exactly', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 60))->toBe(['06:00']);
});

test('lets one window end where the next one begins', function () {
    $early = startTimes('2027-06-01 09:00', '2027-06-01 09:30', 30);
    $late = startTimes('2027-06-01 09:30', '2027-06-01 10:00', 30);

    expect($early)->toBe(['09:00'])
        ->and($late)->toBe(['09:30']);
});

test('drops the start times that are already gone', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 15, '2027-06-01 06:30'))
        ->toBe(['06:30', '06:45']);
});

test('drops the whole window when it lies behind us', function () {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', 15, '2027-06-02 00:00'))->toBe([]);
});

test('refuses a duration of nothing', function (int $durationMinutes) {
    expect(startTimes('2027-06-01 06:00', '2027-06-01 07:00', $durationMinutes))->toBe([]);
})->with([[0], [-15]]);

test('loses an hour of slots when the clocks jump forward', function () {
    $ordinary = startTimes('2027-03-21 01:00', '2027-03-21 05:00', 15);
    $springForward = startTimes('2027-03-28 01:00', '2027-03-28 05:00', 15);

    expect(count($ordinary))->toBe(16)
        ->and(count($springForward))->toBe(12)
        ->and($springForward)->not->toContain('02:00');
});

test('gains an hour of slots when the clocks fall back', function () {
    $ordinary = startTimes('2027-10-24 01:00', '2027-10-24 05:00', 15);
    $fallBack = startTimes('2027-10-31 01:00', '2027-10-31 05:00', 15);

    expect(count($ordinary))->toBe(16)
        ->and(count($fallBack))->toBe(20);
});
