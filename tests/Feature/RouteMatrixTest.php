<?php

use App\Models\Supplier;
use App\Models\User;
use InvalidArgumentException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

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

test('the health check, the app shell and the api reference stay open to everyone', function (string $role) {
    $user = actor($role);

    if ($user !== null) {
        actingAs($user);
    }

    get('/up')->assertOk();
    get('/')->assertOk();
    get('/docs')->assertOk();
})->with(['guest', 'admin', 'staff', 'approved supplier', 'pending supplier']);

test('the approval is checked when signing in, not on every request', function () {
    actingAs(actor('pending supplier'));

    getJson('/api/v1/me')->assertOk();
});
