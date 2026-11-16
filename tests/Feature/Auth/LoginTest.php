<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;

test('logs a user in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'rampe@kidas.test',
        'role' => UserRole::Staff,
    ]);

    postJson('/api/v1/login', [
        'email' => 'rampe@kidas.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', 'rampe@kidas.test')
        ->assertJsonPath('data.role', 'staff');

    assertAuthenticated();
});

test('keeps the password out of the login response', function () {
    User::factory()->create(['email' => 'rampe@kidas.test']);

    postJson('/api/v1/login', [
        'email' => 'rampe@kidas.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

test('rejects a wrong password', function () {
    User::factory()->create(['email' => 'rampe@kidas.test']);

    postJson('/api/v1/login', [
        'email' => 'rampe@kidas.test',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    assertGuest();
});

test('rejects an unknown email address', function () {
    postJson('/api/v1/login', [
        'email' => 'nobody@kidas.test',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    assertGuest();
});

test('requires a valid email and a password', function (array $payload, string $field) {
    postJson('/api/v1/login', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    assertGuest();
})->with([
    'no credentials at all' => [[], 'email'],
    'missing password' => [['email' => 'rampe@kidas.test'], 'password'],
    'missing email' => [['password' => 'password'], 'email'],
    'malformed email' => [['email' => 'not-an-email', 'password' => 'password'], 'email'],
]);

test('throttles repeated failed attempts', function () {
    User::factory()->create(['email' => 'rampe@kidas.test']);

    $credentials = [
        'email' => 'rampe@kidas.test',
        'password' => 'wrong-password',
    ];

    foreach (range(1, 6) as $ignored) {
        postJson('/api/v1/login', $credentials)->assertStatus(422);
    }

    postJson('/api/v1/login', $credentials)->assertStatus(429);
});
