<?php

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'name' => 'Lena Supplier',
        'email' => 'lena@nordfracht.test',
        'password' => 'open-sesame-42',
        'password_confirmation' => 'open-sesame-42',
    ], $overrides);
}

test('registers a supplier as pending', function () {
    postJson('/api/v1/register', registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Lena Supplier')
        ->assertJsonPath('data.email', 'lena@nordfracht.test')
        ->assertJsonPath('data.role', 'supplier');

    $user = User::query()->where('email', 'lena@nordfracht.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Supplier)
        ->and($user->supplier)->not->toBeNull()
        ->and($user->supplier->status)->toBe(SupplierStatus::Pending)
        ->and($user->supplier->company_name)->toBe('Nordfracht GmbH')
        ->and($user->supplier->company_phone)->toBe('+49 40 1234567')
        ->and($user->supplier->approved_at)->toBeNull()
        ->and($user->supplier->approved_by)->toBeNull();
});

test('stores the password hashed and starts no session', function () {
    postJson('/api/v1/register', registrationPayload())->assertCreated();

    $user = User::query()->where('email', 'lena@nordfracht.test')->firstOrFail();

    expect($user->password)->not->toBe('open-sesame-42')
        ->and(Hash::check('open-sesame-42', $user->password))->toBeTrue();

    assertGuest();
});

test('keeps the password out of the registration response', function () {
    postJson('/api/v1/register', registrationPayload())
        ->assertCreated()
        ->assertJsonMissingPath('data.password');
});

test('insists on every field', function (array $overrides, string $field) {
    postJson('/api/v1/register', registrationPayload($overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(User::query()->count())->toBe(0)
        ->and(Supplier::query()->count())->toBe(0);
})->with([
    'company missing' => [['company_name' => ''], 'company_name'],
    'company phone missing' => [['company_phone' => ''], 'company_phone'],
    'name missing' => [['name' => ''], 'name'],
    'email missing' => [['email' => ''], 'email'],
    'email malformed' => [['email' => 'not-an-email'], 'email'],
    'password missing' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'password too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'something-else'], 'password'],
]);

test('refuses an email address that is already taken', function () {
    User::factory()->staff()->create(['email' => 'lena@nordfracht.test']);

    postJson('/api/v1/register', registrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect(Supplier::query()->count())->toBe(0);
});

test('ignores a role and a status smuggled into the request', function () {
    postJson('/api/v1/register', registrationPayload([
        'role' => 'admin',
        'status' => 'approved',
        'approved_at' => now()->toDateTimeString(),
    ]))->assertCreated();

    $user = User::query()->where('email', 'lena@nordfracht.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Supplier)
        ->and($user->supplier->status)->toBe(SupplierStatus::Pending)
        ->and($user->supplier->approved_at)->toBeNull();
});
