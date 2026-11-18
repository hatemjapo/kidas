<?php

use App\Actions\Bookings\DurationForPallets;
use App\Models\PalletRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the migration leaves seven tiers up to the maximum', function () {
    expect(PalletRule::query()->count())->toBe(7)
        ->and(PalletRule::query()->orderBy('max_pallets')->pluck('max_pallets')->all())
        ->toBe([10, 20, 30, 40, 50, 60, 70])
        ->and(PalletRule::query()->max('max_pallets'))->toBe(config('booking.max_pallets'));
});

test('every tier blocks a quarter of an hour more than the one below', function () {
    expect(PalletRule::query()->orderBy('max_pallets')->pluck('duration_minutes')->all())
        ->toBe([15, 30, 45, 60, 75, 90, 105]);
});

test('looks up the duration for a pallet count', function (int $pallets, int $minutes) {
    expect(app(DurationForPallets::class)->getMinutesForPallets($pallets))->toBe($minutes);
})->with([
    'a single pallet' => [1, 15],
    'the top of the first tier' => [10, 15],
    'one pallet into the second tier' => [11, 30],
    'the middle' => [35, 60],
    'one below the maximum' => [69, 105],
    'the maximum' => [70, 105],
]);

test('refuses a pallet count outside the rules', function (int $pallets) {
    expect(fn () => app(DurationForPallets::class)->getMinutesForPallets($pallets))
        ->toThrow(DomainException::class);
})->with([
    'nothing to deliver' => [0],
    'a negative load' => [-5],
    'one above the maximum' => [71],
]);

test('follows the tier that is left after one is removed', function () {
    PalletRule::query()->where('max_pallets', 20)->delete();

    expect(app(DurationForPallets::class)->getMinutesForPallets(11))->toBe(45);
});

test('refuses a duration that breaks the fifteen minute grid', function (int $minutes) {
    expect(fn () => PalletRule::query()->where('max_pallets', 10)->firstOrFail()->update([
        'duration_minutes' => $minutes,
    ]))->toThrow(QueryException::class);
})->with([
    'twenty minutes' => [20],
    'nothing at all' => [0],
    'one minute' => [1],
]);

test('accepts any multiple of fifteen, however long', function () {
    PalletRule::query()->where('max_pallets', 70)->firstOrFail()->update(['duration_minutes' => 240]);

    expect(app(DurationForPallets::class)->getMinutesForPallets(70))->toBe(240);
});

test('refuses a second tier for the same pallet count', function () {
    expect(fn () => DB::table('pallet_rules')->insert([
        'max_pallets' => 10,
        'duration_minutes' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('keeps the pallet count out of mass assignment', function () {
    $tier = PalletRule::query()->where('max_pallets', 10)->firstOrFail();

    $tier->update(['max_pallets' => 15, 'duration_minutes' => 30]);

    expect($tier->refresh()->max_pallets)->toBe(10)
        ->and($tier->duration_minutes)->toBe(30);
});

test('the model hands back the smallest tier that carries the load', function (int $pallets, int $maxPallets) {
    $rule = PalletRule::getRuleForPallets($pallets);

    expect($rule)->not->toBeNull()
        ->and($rule->max_pallets)->toBe($maxPallets);
})->with([
    'a single pallet' => [1, 10],
    'the top of a tier' => [20, 20],
    'one pallet above it' => [21, 30],
    'the maximum' => [70, 70],
]);

test('the model hands back nothing when no tier carries the load', function () {
    expect(PalletRule::getRuleForPallets(71))->toBeNull();
});
