<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('normalizes whatever was typed', function (string $typed, string $stored) {
    expect(Vehicle::normalizePlate($typed))->toBe($stored);
})->with([
    'a german plate with a hyphen' => ['HH-AB 1234', 'HHAB1234'],
    'lower case' => ['hh-ab 1234', 'HHAB1234'],
    'extra spaces' => ['  HH  AB  1234  ', 'HHAB1234'],
    'dots and slashes' => ['HH.AB/1234', 'HHAB1234'],
    'an umlaut in the district' => ['LÖ-AB 123', 'LÖAB123'],
    'nothing but punctuation' => ['---', ''],
]);

test('stores a plate in its normalized form', function () {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    postJson('/api/v1/vehicles', ['license_plate' => 'hh-ab 1234'])
        ->assertCreated()
        ->assertJsonPath('data.license_plate', 'HHAB1234');

    expect($supplier->vehicles()->pluck('license_plate')->all())->toBe(['HHAB1234']);
});

test('normalizes on every write, not only through the request', function () {
    $supplier = Supplier::factory()->approved()->create();

    $vehicle = $supplier->vehicles()->create(['license_plate' => 'hh-ab 1234']);

    expect($vehicle->refresh()->license_plate)->toBe('HHAB1234');
});

test('refuses the same plate written differently', function () {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    postJson('/api/v1/vehicles', ['license_plate' => 'HHAB1234'])->assertCreated();

    postJson('/api/v1/vehicles', ['license_plate' => 'hh-ab 1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('license_plate')
        ->assertJsonPath('errors.license_plate.0', 'This license plate is already saved.');

    expect($supplier->vehicles()->count())->toBe(1);
});

test('lets another supplier keep the same lorry', function () {
    $first = Supplier::factory()->approved()->create();
    $second = Supplier::factory()->approved()->create();

    actingAs($first->user);
    postJson('/api/v1/vehicles', ['license_plate' => 'HHAB1234'])->assertCreated();

    actingAs($second->user);
    postJson('/api/v1/vehicles', ['license_plate' => 'HHAB1234'])->assertCreated();

    expect(Vehicle::query()->where('license_plate', 'HHAB1234')->count())->toBe(2);
});

test('insists on something that survives the normalization', function (string $typed) {
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    postJson('/api/v1/vehicles', ['license_plate' => $typed])
        ->assertStatus(422)
        ->assertJsonValidationErrors('license_plate');

    expect($supplier->vehicles()->count())->toBe(0);
})->with([
    'empty' => [''],
    'only punctuation' => ['- . /'],
    'too long' => ['HHAB1234567890123'],
]);

test('lists only the own vehicles, sorted', function () {
    $supplier = Supplier::factory()->approved()->create();
    Vehicle::factory()->for($supplier)->create(['license_plate' => 'HHZZ1']);
    Vehicle::factory()->for($supplier)->create(['license_plate' => 'HHAA1']);
    Vehicle::factory()->create(['license_plate' => 'MMMM1']);

    actingAs($supplier->user);

    getJson('/api/v1/vehicles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.license_plate', 'HHAA1')
        ->assertJsonPath('data.1.license_plate', 'HHZZ1');
});

test('removes an own vehicle', function () {
    $supplier = Supplier::factory()->approved()->create();
    $vehicle = Vehicle::factory()->for($supplier)->create();

    actingAs($supplier->user);

    deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertNoContent();

    expect(Vehicle::query()->count())->toBe(0);
});

test('answers 404 for a vehicle of another supplier', function () {
    $supplier = Supplier::factory()->approved()->create();
    $foreign = Vehicle::factory()->create();

    actingAs($supplier->user);

    deleteJson("/api/v1/vehicles/{$foreign->id}")->assertNotFound();

    expect(Vehicle::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('drops the vehicles when the supplier is deleted', function () {
    $supplier = Supplier::factory()->approved()->create();
    Vehicle::factory()->for($supplier)->count(3)->create();

    $supplier->delete();

    expect(Vehicle::query()->count())->toBe(0);
});

test('answers a guest with 401', function () {
    getJson('/api/v1/vehicles')->assertUnauthorized();
    postJson('/api/v1/vehicles')->assertUnauthorized();
    deleteJson('/api/v1/vehicles/1')->assertUnauthorized();
});

test('keeps the other roles out of the vehicles', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/vehicles')->assertForbidden();
    postJson('/api/v1/vehicles')->assertForbidden();
    deleteJson('/api/v1/vehicles/1')->assertForbidden();
})->with(['admin', 'staff']);
