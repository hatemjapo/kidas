<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

test('answers a guest with 401', function () {
    getJson('/api/v1/me')->assertUnauthorized();
});

test('returns the signed in user with the role', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    actingAs($user);

    getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', $role->value);
})->with([
    UserRole::Admin,
    UserRole::Staff,
    UserRole::Supplier,
]);

test('keeps the password out of the user endpoint', function () {
    actingAs(User::factory()->create());

    getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});
