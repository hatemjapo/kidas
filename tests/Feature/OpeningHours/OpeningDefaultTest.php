<?php

use App\Enums\Weekday;
use App\Models\OpeningDefault;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the migration leaves one row per weekday', function () {
    expect(OpeningDefault::query()->count())->toBe(7)
        ->and(OpeningDefault::query()->orderBy('weekday')->pluck('weekday')->all())->toBe(Weekday::cases());
});

test('the week runs from six to six on working days and stays closed on the weekend', function (Weekday $weekday, bool $closed) {
    $day = OpeningDefault::query()->where('weekday', $weekday)->firstOrFail();

    expect($day->is_closed)->toBe($closed)
        ->and($day->opens_at)->toBe('06:00:00')
        ->and($day->closes_at)->toBe('18:00:00');
})->with([
    [Weekday::Monday, false],
    [Weekday::Tuesday, false],
    [Weekday::Wednesday, false],
    [Weekday::Thursday, false],
    [Weekday::Friday, false],
    [Weekday::Saturday, true],
    [Weekday::Sunday, true],
]);

test('reads the weekday as an enum', function () {
    $day = OpeningDefault::query()->where('weekday', 1)->firstOrFail();

    expect($day->weekday)->toBe(Weekday::Monday)
        ->and($day->weekday->label())->toBe('Monday');
});

test('refuses a window that closes before it opens', function () {
    expect(fn () => OpeningDefault::query()->where('weekday', 1)->firstOrFail()->update([
        'opens_at' => '18:00:00',
        'closes_at' => '06:00:00',
    ]))->toThrow(QueryException::class);
});

test('refuses a window that closes when it opens', function () {
    expect(fn () => OpeningDefault::query()->where('weekday', 1)->firstOrFail()->update([
        'opens_at' => '06:00:00',
        'closes_at' => '06:00:00',
    ]))->toThrow(QueryException::class);
});

test('accepts any times on a day that is closed', function () {
    $day = OpeningDefault::query()->where('weekday', 6)->firstOrFail();

    $day->update(['is_closed' => true, 'opens_at' => '18:00:00', 'closes_at' => '06:00:00']);

    expect($day->refresh()->is_closed)->toBeTrue();
});

test('refuses a second row for the same weekday', function () {
    expect(fn () => DB::table('opening_defaults')->insert([
        'weekday' => 1,
        'opens_at' => '07:00:00',
        'closes_at' => '17:00:00',
        'is_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('keeps the weekday out of mass assignment', function () {
    $day = OpeningDefault::query()->where('weekday', 1)->firstOrFail();

    $day->update(['weekday' => 4, 'opens_at' => '07:00:00']);

    expect($day->refresh()->weekday)->toBe(Weekday::Monday)
        ->and($day->opens_at)->toBe('07:00:00');
});
