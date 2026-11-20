<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\json;

/**
 * Who may reach which endpoint. Every route of the API has to stand here, and
 * nothing may stand here that the application does not serve; two tests keep
 * that promise.
 *
 * @return array<string, list<string>>
 */
function roleMatrix(): array
{
    return [
        'POST /register' => ['guest', 'supplier', 'staff', 'admin'],
        'POST /login' => ['guest', 'supplier', 'staff', 'admin'],
        'POST /logout' => ['supplier', 'staff', 'admin'],
        'GET /me' => ['supplier', 'staff', 'admin'],

        'GET /profile' => ['supplier'],
        'PATCH /profile' => ['supplier'],
        'GET /vehicles' => ['supplier'],
        'POST /vehicles' => ['supplier'],
        'DELETE /vehicles/{vehicle}' => ['supplier'],

        'GET /slots' => ['supplier', 'admin'],

        'GET /bookings' => ['supplier'],
        'POST /bookings' => ['supplier'],
        'GET /bookings/{bookingId}' => ['supplier'],
        'PATCH /bookings/{bookingId}' => ['supplier'],
        'POST /bookings/{bookingId}/cancel' => ['supplier'],
        'GET /bookings/{bookingId}/status' => ['supplier'],

        'GET /schedule' => ['staff', 'admin'],
        'POST /bookings/{bookingId}/arrival' => ['staff', 'admin'],
        'PATCH /bookings/{bookingId}/arrival' => ['staff', 'admin'],
        'POST /bookings/{bookingId}/plate-verification' => ['staff', 'admin'],
        'POST /bookings/{bookingId}/handling' => ['staff', 'admin'],
        'POST /bookings/{bookingId}/completion' => ['staff', 'admin'],
        'POST /bookings/{bookingId}/no-show' => ['staff', 'admin'],

        'GET /suppliers' => ['admin'],
        'GET /suppliers/{supplier}/vehicles' => ['admin'],
        'POST /admin/bookings' => ['admin'],
        'PATCH /admin/bookings/{bookingId}' => ['admin'],
        'POST /admin/bookings/{bookingId}/cancel' => ['admin'],
    ];
}

/**
 * @return list<string>
 */
function registeredEndpoints(): array
{
    $endpoints = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $endpoints[] = $method.' '.substr($route->uri(), strlen('api/v1'));
        }
    }

    sort($endpoints);

    return $endpoints;
}

function signIn(string $role): void
{
    $user = match ($role) {
        'guest' => null,
        'admin' => User::factory()->admin()->create(),
        'staff' => User::factory()->staff()->create(),
        'supplier' => Supplier::factory()->approved()->create()->user,
        default => throw new InvalidArgumentException("Unknown actor [{$role}]."),
    };

    if ($user instanceof User) {
        actingAs($user);
    }
}

test('the matrix names every endpoint the application serves', function () {
    $named = array_keys(roleMatrix());

    sort($named);

    expect($named)->toBe(registeredEndpoints());
});

test('every role is let in or turned away as the matrix says', function (string $endpoint, array $allowed) {
    [$method, $path] = explode(' ', $endpoint);

    $url = '/api/v1'.str_replace(['{bookingId}', '{vehicle}', '{supplier}'], '1', $path);

    foreach (['guest', 'supplier', 'staff', 'admin'] as $role) {
        signIn($role);

        $status = json($method, $url, [])->getStatusCode();

        if (in_array($role, $allowed, true)) {
            expect($status)->not->toBeIn([401, 403]);

            continue;
        }

        expect($status)->toBe($role === 'guest' ? 401 : 403);
    }
})->with(fn () => collect(roleMatrix())
    ->map(fn (array $allowed, string $endpoint): array => [$endpoint, $allowed])
    ->values()
    ->all());
