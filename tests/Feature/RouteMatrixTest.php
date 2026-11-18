<?php

use App\Models\Supplier;
use App\Models\User;
use InvalidArgumentException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function actor(string $role): ?User
{
    return match ($role) {
        'guest' => null,
        'admin' => User::factory()->admin()->create(),
        'staff' => User::factory()->staff()->create(),
        'approved supplier' => Supplier::factory()->approved()->create()->user,
        'pending supplier' => Supplier::factory()->pending()->create()->user,
        default => throw new InvalidArgumentException("Unknown actor [{$role}]."),
    };
}

test('the panel belongs to admins alone', function (string $role, int $status) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    get('/admin')->assertStatus($status);
    get('/admin/suppliers')->assertStatus($status);
    get('/admin/docks')->assertStatus($status);
    get('/admin/opening-defaults')->assertStatus($status);
    get('/admin/shift-defaults')->assertStatus($status);
    get('/admin/days')->assertStatus($status);
    get('/admin/pallet-rules')->assertStatus($status);
})->with([
    ['guest', 302],
    ['admin', 200],
    ['staff', 403],
    ['approved supplier', 403],
    ['pending supplier', 403],
]);

test('the session endpoints answer every signed in role', function (string $role, int $status) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    getJson('/api/v1/me')->assertStatus($status);
})->with([
    ['guest', 401],
    ['admin', 200],
    ['staff', 200],
    ['approved supplier', 200],
]);

test('logging out is possible for every signed in role', function (string $role, int $status) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    postJson('/api/v1/logout')->assertStatus($status);
})->with([
    ['guest', 401],
    ['admin', 204],
    ['staff', 204],
    ['approved supplier', 204],
]);

test('the profile belongs to suppliers alone', function (string $role, int $status) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    getJson('/api/v1/profile')->assertStatus($status);
    getJson('/api/v1/vehicles')->assertStatus($status);
    getJson('/api/v1/slots?type=inbound&pallets=24')->assertStatus($status);
    postJson('/api/v1/bookings')->assertStatus($status === 200 ? 422 : $status);
    getJson('/api/v1/bookings')->assertStatus($status);
    patchJson('/api/v1/bookings/1', ['pallets' => 10])->assertStatus($status === 200 ? 404 : $status);
})->with([
    ['guest', 401],
    ['admin', 403],
    ['staff', 403],
    ['approved supplier', 200],
]);

test('registration and login stay open to everyone', function (string $role) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    postJson('/api/v1/register', [])->assertStatus(422);
    postJson('/api/v1/login', [])->assertStatus(422);
})->with(['guest', 'admin', 'staff', 'approved supplier', 'pending supplier']);

test('the health check and the app shell stay open to everyone', function (string $role) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    get('/up')->assertOk();
    get('/')->assertOk();
})->with(['guest', 'admin', 'staff', 'approved supplier', 'pending supplier']);

test('the approval is checked when signing in, not on every request', function () {
    actingAs(actor('pending supplier'));

    getJson('/api/v1/me')->assertOk();
});
