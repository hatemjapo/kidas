<?php

use App\Models\Dock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the migration leaves ten active docks behind', function () {
    expect(Dock::query()->count())->toBe(10)
        ->and(Dock::query()->pluck('number')->all())->toBe(range(1, 10))
        ->and(Dock::query()->where('is_active', false)->exists())->toBeFalse();
});

test('every dock is named after its number', function () {
    expect(Dock::query()->orderBy('number')->pluck('name')->all())
        ->toBe(['dock1', 'dock2', 'dock3', 'dock4', 'dock5', 'dock6', 'dock7', 'dock8', 'dock9', 'dock10']);
});

test('refuses a second dock with the same number', function () {
    expect(fn () => DB::table('docks')->insert([
        'number' => 1,
        'name' => 'dock1 again',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('keeps the number and the name out of mass assignment', function () {
    $dock = Dock::query()->where('number', 1)->firstOrFail();

    $dock->update(['number' => 99, 'name' => 'renamed', 'is_active' => false]);

    expect($dock->refresh()->number)->toBe(1)
        ->and($dock->name)->toBe('dock1')
        ->and($dock->is_active)->toBeFalse();
});

test('reads the active flag as a boolean', function () {
    $dock = Dock::query()->where('number', 1)->firstOrFail();

    expect($dock->is_active)->toBeTrue();

    $dock->update(['is_active' => false]);

    expect($dock->refresh()->is_active)->toBeFalse();
});
