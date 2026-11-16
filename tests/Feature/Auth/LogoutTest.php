<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('rejects a logout without a session', function () {
    postJson('/api/v1/logout')->assertUnauthorized();

    assertGuest('web');
});

test('logs the current user out', function () {
    actingAs(User::factory()->create());

    postJson('/api/v1/logout')->assertNoContent();

    assertGuest('web');
});

test('ends the session so the user endpoint stops answering', function () {
    User::factory()->staff()->create(['email' => 'rampe@kidas.test']);

    postJson('/api/v1/login', [
        'email' => 'rampe@kidas.test',
        'password' => 'password',
    ])->assertOk();

    Auth::forgetGuards();

    getJson('/api/v1/me')->assertOk();

    postJson('/api/v1/logout')->assertNoContent();

    Auth::forgetGuards();

    getJson('/api/v1/me')->assertUnauthorized();
});
