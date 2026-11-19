<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

test('books for a supplier', function (string $type) {
    openedDay($type === 'inbound' ? DockUsage::Inbound : DockUsage::Outbound);
    $supplier = Supplier::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    postJson('/api/v1/admin/bookings', adminPayload($supplier, ['type' => $type]))
        ->assertCreated()
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.status', 'booked')
        ->assertJsonPath('data.license_plate', 'HHAB1234');

    $booking = Booking::query()->firstOrFail();

    expect($booking->supplier_id)->toBe($supplier->id)
        ->and($booking->created_by)->toBe($admin->id)
        ->and($booking->driver_phone)->toBe('+49 170 1234567');
})->with(['inbound', 'outbound']);

test('books without the three hours of distance', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier))->assertCreated();
});

test('a supplier is still held to the three hours at that time', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    postJson('/api/v1/bookings', [
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => '2027-06-02T06:00:00+02:00',
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'HHAB1234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');
});

test('offers the admin a start time the supplier does not see', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    $forSupplier = getJson('/api/v1/slots?type=inbound&pallets=10')->assertOk()->json('data.0.slots');

    actingAs(User::factory()->admin()->create());

    $forAdmin = getJson('/api/v1/slots?type=inbound&pallets=10')->assertOk()->json('data.0.slots');

    expect($forAdmin[0]['starts_at'])->toBe('2027-06-02T04:00:00Z')
        ->and($forSupplier[0]['starts_at'])->toBe('2027-06-02T06:00:00Z')
        ->and(count($forAdmin))->toBeGreaterThan(count($forSupplier));
});

test('keeps a day that was never released shut for the admin as well', function () {
    $day = openedDay();
    $day->is_released = false;
    $day->save();

    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier))
        ->assertStatus(422)
        ->assertJsonPath('errors.starts_at.0', 'This day is not open for booking.');

    expect(Booking::query()->count())->toBe(0);
});

test('keeps the dock type', function () {
    openedDay(DockUsage::Outbound);
    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier, ['type' => 'inbound']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');
});

test('takes a plate the supplier already has', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $vehicle = $supplier->vehicles()->create(['license_plate' => 'HHCD5678']);

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier, [
        'vehicle_id' => $vehicle->id,
        'license_plate' => null,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.license_plate', 'HHCD5678');
});

test('refuses a vehicle of somebody else', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $stranger = Supplier::factory()->approved()->create();
    $vehicle = $stranger->vehicles()->create(['license_plate' => 'HHCD5678']);

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier, [
        'vehicle_id' => $vehicle->id,
        'license_plate' => null,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.vehicle_id.0', 'This vehicle is not one of theirs.');

    expect(Booking::query()->count())->toBe(0);
});

test('remembers a new plate for that supplier', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $stranger = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier))->assertCreated();

    expect($supplier->vehicles()->pluck('license_plate')->all())->toBe(['HHAB1234'])
        ->and($stranger->vehicles()->count())->toBe(0);
});

test('insists on an approved supplier', function () {
    openedDay();
    $pending = Supplier::factory()->pending()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($pending))
        ->assertStatus(422)
        ->assertJsonPath('errors.supplier_id.0', 'This supplier is not approved.');
});

test('insists on a complete booking', function (array $overrides, string $field) {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier, $overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no supplier' => [['supplier_id' => null], 'supplier_id'],
    'no type' => [['type' => null], 'type'],
    'no pallets' => [['pallets' => null], 'pallets'],
    'too many pallets' => [['pallets' => 71], 'pallets'],
    'no start' => [['starts_at' => null], 'starts_at'],
    'no reference' => [['order_number' => ''], 'order_number'],
    'no phone' => [['driver_phone' => ''], 'driver_phone'],
    'no lorry at all' => [['license_plate' => null], 'license_plate'],
]);

test('answers 409 once every dock is taken', function () {
    openedDay();
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    foreach (range(1, 10) as $ignored) {
        postJson('/api/v1/admin/bookings', adminPayload(Supplier::factory()->approved()->create()))
            ->assertCreated();
    }

    postJson('/api/v1/admin/bookings', adminPayload(Supplier::factory()->approved()->create()))
        ->assertStatus(409);
});

test('the supplier owns the booking afterwards', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());
    postJson('/api/v1/admin/bookings', adminPayload($supplier, [
        'starts_at' => '2027-06-02T10:00:00+02:00',
    ]))->assertCreated();

    $booking = Booking::query()->firstOrFail();

    actingAs($supplier->user);

    getJson('/api/v1/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', 'PO-4711');

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

test('hands the admin the approved suppliers', function () {
    Supplier::factory()->approved()->create(['company_name' => 'Nordfracht GmbH']);
    Supplier::factory()->approved()->create(['company_name' => 'Aalbert Logistik']);
    Supplier::factory()->pending()->create(['company_name' => 'Noch nicht frei']);

    actingAs(User::factory()->admin()->create());

    $companies = array_column(getJson('/api/v1/suppliers')->assertOk()->json('data'), 'company_name');

    expect($companies)->toBe(['Aalbert Logistik', 'Nordfracht GmbH']);
});

test('hands the admin the plates of one supplier', function () {
    $supplier = Supplier::factory()->approved()->create();
    $supplier->vehicles()->create(['license_plate' => 'HHZZ9999']);
    $supplier->vehicles()->create(['license_plate' => 'HHAA1111']);
    Supplier::factory()->approved()->create()->vehicles()->create(['license_plate' => 'HHXX0000']);

    actingAs(User::factory()->admin()->create());

    $plates = array_column(getJson("/api/v1/suppliers/{$supplier->id}/vehicles")->assertOk()->json('data'), 'license_plate');

    expect($plates)->toBe(['HHAA1111', 'HHZZ9999']);
});

test('keeps the plates of a supplier who is not approved out of reach', function () {
    $pending = Supplier::factory()->pending()->create();

    actingAs(User::factory()->admin()->create());

    getJson("/api/v1/suppliers/{$pending->id}/vehicles")->assertNotFound();
});

test('keeps everybody else out of the admin booking', function (string $state) {
    $user = $state === 'supplier'
        ? Supplier::factory()->approved()->create()->user
        : User::factory()->staff()->create();

    actingAs($user);

    postJson('/api/v1/admin/bookings')->assertForbidden();
    getJson('/api/v1/suppliers')->assertForbidden();
    getJson('/api/v1/suppliers/1/vehicles')->assertForbidden();
})->with(['supplier', 'staff']);

test('keeps the warehouse out of the slots', function () {
    actingAs(User::factory()->staff()->create());

    getJson('/api/v1/slots?type=inbound&pallets=10')->assertForbidden();
});

test('answers a guest with 401', function () {
    postJson('/api/v1/admin/bookings')->assertUnauthorized();
    getJson('/api/v1/suppliers')->assertUnauthorized();
    getJson('/api/v1/suppliers/1/vehicles')->assertUnauthorized();
});

test('does not remember a plate twice', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $supplier->vehicles()->create(['license_plate' => 'HHAB1234']);

    actingAs(User::factory()->admin()->create());

    postJson('/api/v1/admin/bookings', adminPayload($supplier))->assertCreated();

    expect(Vehicle::query()->where('supplier_id', $supplier->id)->count())->toBe(1);
});

test('a slot the admin books for the next hour is already past the supplier deadline', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();

    actingAs(User::factory()->admin()->create());
    postJson('/api/v1/admin/bookings', adminPayload($supplier))->assertCreated();

    $booking = Booking::query()->firstOrFail();

    actingAs($supplier->user);

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
});
