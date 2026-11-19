<?php

use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

test('stops a supplier who hammers the booking endpoint', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(Supplier::factory()->approved()->create()->user);

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/bookings', [])->assertStatus(422);
    }

    postJson('/api/v1/bookings', [])->assertStatus(429);
});

test('counts per account, not for everybody at once', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(Supplier::factory()->approved()->create()->user);

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/bookings', [])->assertStatus(422);
    }

    postJson('/api/v1/bookings', [])->assertStatus(429);

    actingAs(Supplier::factory()->approved()->create()->user);

    postJson('/api/v1/bookings', [])->assertStatus(422);
});

test('lets the same account book again a minute later', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(Supplier::factory()->approved()->create()->user);

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/bookings', [])->assertStatus(422);
    }

    postJson('/api/v1/bookings', [])->assertStatus(429);

    travelTo(CarbonImmutable::parse('2027-06-02 05:02', 'Europe/Berlin'));

    postJson('/api/v1/bookings', [])->assertStatus(422);
});

test('counts changing and cancelling against the same limit', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(Supplier::factory()->approved()->create()->user);

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/bookings/1/cancel', [])->assertStatus(404);
    }

    postJson('/api/v1/bookings', [])->assertStatus(429);
});

test('leaves reading alone', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(Supplier::factory()->approved()->create()->user);

    foreach (range(1, $limit + 5) as $ignored) {
        getJson('/api/v1/bookings')->assertOk();
    }
});

test('gives the warehouse more room than a supplier', function () {
    $warehouse = (int) config('booking.warehouse_actions_per_minute');
    $bookings = (int) config('booking.writes_per_minute');

    expect($warehouse)->toBeGreaterThan($bookings);

    actingAs(User::factory()->staff()->create());

    foreach (range(1, $bookings + 1) as $ignored) {
        postJson('/api/v1/bookings/1/handling')->assertStatus(404);
    }
});

test('still guards signing in and registering', function () {
    foreach (range(1, 6) as $ignored) {
        postJson('/api/v1/login', ['email' => 'nobody@kidas.test', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    postJson('/api/v1/login', ['email' => 'nobody@kidas.test', 'password' => 'wrong'])
        ->assertStatus(429);
});

test('guards registering as well', function () {
    foreach (range(1, 6) as $ignored) {
        postJson('/api/v1/register', [])->assertStatus(422);
    }

    postJson('/api/v1/register', [])->assertStatus(429);
});

test('stops an admin who hammers the booking endpoint', function () {
    $limit = (int) config('booking.writes_per_minute');

    actingAs(User::factory()->admin()->create());

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/admin/bookings', [])->assertStatus(422);
    }

    postJson('/api/v1/admin/bookings', [])->assertStatus(429);
    postJson('/api/v1/admin/bookings/1/cancel', [])->assertStatus(429);
});

test('stops a warehouse account that hammers the handling endpoints', function () {
    $limit = (int) config('booking.warehouse_actions_per_minute');

    actingAs(User::factory()->staff()->create());

    foreach (range(1, $limit) as $ignored) {
        postJson('/api/v1/bookings/1/handling')->assertStatus(404);
    }

    postJson('/api/v1/bookings/1/handling')->assertStatus(429);
    postJson('/api/v1/bookings/1/arrival', ['license_plate' => 'HHAB1234'])->assertStatus(429);
});

test('every writing endpoint carries a limit', function () {
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        if (array_diff($route->methods(), ['GET', 'HEAD']) === []) {
            continue;
        }

        $limited = collect($route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, 'throttle:'));

        expect($limited)->toBeTrue("{$route->methods()[0]} {$route->uri()} is not limited");
    }
});
