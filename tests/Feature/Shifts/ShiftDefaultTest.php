<?php

use App\Enums\Shift;
use App\Models\ShiftDefault;
use App\Rules\DoesNotOverlapOtherShift;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

test('the migration leaves one row per shift', function () {
    expect(ShiftDefault::query()->count())->toBe(2)
        ->and(ShiftDefault::query()->orderBy('starts_at')->pluck('shift')->all())
        ->toBe([Shift::Early, Shift::Late]);
});

test('the day runs from six to six in two shifts', function (Shift $shift, string $startsAt, string $endsAt) {
    $row = ShiftDefault::query()->where('shift', $shift)->firstOrFail();

    expect($row->starts_at)->toBe($startsAt)
        ->and($row->ends_at)->toBe($endsAt);
})->with([
    [Shift::Early, '06:00:00', '14:00:00'],
    [Shift::Late, '14:00:00', '18:00:00'],
]);

test('refuses a shift that ends before it starts', function () {
    expect(fn () => ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail()->update([
        'starts_at' => '14:00:00',
        'ends_at' => '06:00:00',
    ]))->toThrow(QueryException::class);
});

test('refuses a second row for the same shift', function () {
    expect(fn () => DB::table('shift_defaults')->insert([
        'shift' => 'early',
        'starts_at' => '05:00:00',
        'ends_at' => '09:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('keeps the shift out of mass assignment', function () {
    $early = ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail();

    $early->update(['shift' => 'late', 'starts_at' => '07:00:00']);

    expect($early->refresh()->shift)->toBe(Shift::Early)
        ->and($early->starts_at)->toBe('07:00:00');
});

function failsOverlapRule(Shift $shift, string $startsAt, string $endsAt): bool
{
    return Validator::make(
        ['ends_at' => $endsAt],
        ['ends_at' => [new DoesNotOverlapOtherShift($shift, $startsAt)]],
    )->fails();
}

test('the rule judges a window against the other shift', function (Shift $shift, string $startsAt, string $endsAt, bool $rejected) {
    expect(failsOverlapRule($shift, $startsAt, $endsAt))->toBe($rejected);
})->with([
    'early reaching into the late shift' => [Shift::Early, '06:00', '15:00', true],
    'early ending exactly when the late one starts' => [Shift::Early, '06:00', '14:00', false],
    'early swallowing the late shift' => [Shift::Early, '06:00', '20:00', true],
    'early staying well before' => [Shift::Early, '06:00', '12:00', false],
    'late starting inside the early shift' => [Shift::Late, '13:00', '18:00', true],
    'late starting exactly when the early one ends' => [Shift::Late, '14:00', '18:00', false],
    'late lying entirely inside the early shift' => [Shift::Late, '08:00', '09:00', true],
    'late pushed past the early shift' => [Shift::Late, '15:00', '22:00', false],
]);

test('the rule names the shift that is in the way', function () {
    $validator = Validator::make(
        ['ends_at' => '15:00'],
        ['ends_at' => [new DoesNotOverlapOtherShift(Shift::Early, '06:00')]],
    );

    expect($validator->errors()->first('ends_at'))
        ->toBe('This overlaps the Late shift from 14:00 to 18:00.');
});
