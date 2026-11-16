<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'role:staff'])
        ->get('/api/v1/testing/warehouse', fn () => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'role:staff,admin'])
        ->get('/api/v1/testing/shared', fn () => response()->json(['ok' => true]));
});

test('lets the named role through', function () {
    actingAs(User::factory()->staff()->create());

    getJson('/api/v1/testing/warehouse')->assertOk();
});

test('turns every other role away', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/testing/warehouse')
        ->assertForbidden()
        ->assertJsonPath('message', 'This area belongs to another role.');
})->with(['admin', 'supplier']);

test('turns an approved supplier away as well', function () {
    actingAs(Supplier::factory()->approved()->create()->user);

    getJson('/api/v1/testing/warehouse')->assertForbidden();
});

test('answers a guest with 401 instead of 403', function () {
    getJson('/api/v1/testing/warehouse')->assertUnauthorized();
});

test('accepts any of several named roles', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/testing/shared')->assertOk();
})->with(['staff', 'admin']);

test('still turns away a role that is not named', function () {
    actingAs(User::factory()->supplier()->create());

    getJson('/api/v1/testing/shared')->assertForbidden();
});
