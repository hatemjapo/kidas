<?php

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;

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

test('keeps a default driver phone for the next booking', function () {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Supplier',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'driver_phone' => '+49 170 1234567',
    ])
        ->assertOk()
        ->assertJsonPath('data.driver_phone', '+49 170 1234567');

    expect($supplier->refresh()->driver_phone)->toBe('+49 170 1234567');
});

test('lets the default driver phone be dropped again', function () {
    $supplier = Supplier::factory()->approved()->create(['driver_phone' => '+49 170 1234567']);

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Supplier',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
    ])
        ->assertOk()
        ->assertJsonPath('data.driver_phone', null);

    expect($supplier->refresh()->driver_phone)->toBeNull();
});

test('refuses a driver phone that is far too long', function () {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Lena Supplier',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'driver_phone' => str_repeat('9', 51),
    ])->assertStatus(422)->assertJsonValidationErrors('driver_phone');
});

test('a booking keeps the number it was made with', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create(['driver_phone' => '+49 170 1234567']);

    CarbonImmutable::setTestNow('2027-06-02 03:00:00');

    actingAs($supplier->user);

    Pest\Laravel\postJson('/api/v1/bookings', [
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => '2027-06-02T10:00:00+02:00',
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 171 9999999',
        'license_plate' => 'HHAB1234',
    ])->assertCreated();

    patchJson('/api/v1/profile', [
        'name' => 'Lena Supplier',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1234567',
        'driver_phone' => '+49 172 0000000',
    ])->assertOk();

    expect(Booking::query()->firstOrFail()->driver_phone)->toBe('+49 171 9999999');

    CarbonImmutable::setTestNow();
});
