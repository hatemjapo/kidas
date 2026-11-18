<?php

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

function approvedSupplier(): Supplier
{
    return Supplier::factory()->approved()->create([
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
    ]);
}

test('a supplier reads their own profile', function () {
    $supplier = approvedSupplier();

    actingAs($supplier->user);

    getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.name', $supplier->user->name)
        ->assertJsonPath('data.email', $supplier->user->email)
        ->assertJsonPath('data.company_name', 'Nordfracht GmbH')
        ->assertJsonPath('data.company_phone', '+49 40 1234567')
        ->assertJsonPath('data.status', 'approved');
});

test('a supplier changes company, phone and contact name', function () {
    $supplier = approvedSupplier();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Neu',
        'company_name' => 'Nordfracht Logistik GmbH',
        'company_phone' => '+49 40 7654321',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Lena Neu')
        ->assertJsonPath('data.company_name', 'Nordfracht Logistik GmbH');

    expect($supplier->refresh()->company_name)->toBe('Nordfracht Logistik GmbH')
        ->and($supplier->company_phone)->toBe('+49 40 7654321')
        ->and($supplier->user->refresh()->name)->toBe('Lena Neu');
});

test('the email stays as it is', function () {
    $supplier = approvedSupplier();
    $email = $supplier->user->email;

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Neu',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'email' => 'somewhere@else.test',
    ])->assertOk();

    expect($supplier->user->refresh()->email)->toBe($email);
});

test('insists on every field', function (array $overrides, string $field) {
    $supplier = approvedSupplier();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', array_merge([
        'name' => 'Lena Neu',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
    ], $overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect($supplier->refresh()->company_name)->toBe('Nordfracht GmbH');
})->with([
    'no name' => [['name' => ''], 'name'],
    'no company' => [['company_name' => ''], 'company_name'],
    'no phone' => [['company_phone' => ''], 'company_phone'],
]);

test('ignores a status and a role smuggled into the request', function () {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Neu',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'status' => 'rejected',
        'role' => 'admin',
        'approved_by' => 99,
    ])->assertOk();

    expect($supplier->refresh()->status)->toBe(SupplierStatus::Approved)
        ->and($supplier->user->refresh()->role)->toBe(UserRole::Supplier);
});

test('answers a guest with 401', function () {
    getJson('/api/v1/profile')->assertUnauthorized();
    patchJson('/api/v1/profile')->assertUnauthorized();
});

test('keeps the other roles out of the profile', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/profile')->assertForbidden();
    patchJson('/api/v1/profile')->assertForbidden();
})->with(['admin', 'staff']);

test('lets a supplier change only their own profile', function (string $state, bool $allowed) {
    $supplier = approvedSupplier();
    $other = $state === 'owner' ? $supplier->user : User::factory()->{$state}()->create();

    expect($other->can('update', $supplier))->toBe($allowed);
})->with([
    'the owner' => ['owner', true],
    'an admin' => ['admin', true],
    'another supplier' => ['supplier', false],
    'warehouse staff' => ['staff', false],
]);
