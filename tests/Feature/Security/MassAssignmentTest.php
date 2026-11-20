<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

test('a supplier cannot decide their own booking', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $stranger = Supplier::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    actingAs($supplier->user);

    postJson('/api/v1/bookings', [
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => '2027-06-02T10:00:00+02:00',
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'HHAB1234',
        'status' => 'completed',
        'supplier_id' => $stranger->id,
        'dock_id' => Dock::query()->where('number', 9)->firstOrFail()->id,
        'created_by' => $admin->id,
        'arrived_at' => '2027-06-02T09:00:00Z',
        'arrived_plate' => 'HHZZ9999',
        'plate_mismatch' => true,
        'plate_verified_by' => $admin->id,
        'cancelled_at' => '2027-06-02T09:00:00Z',
        'cancel_reason' => 'never happened',
    ])->assertCreated();

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Booked)
        ->and($booking->supplier_id)->toBe($supplier->id)
        ->and($booking->created_by)->toBe($supplier->user->id)
        ->and($booking->dock->number)->toBe(1)
        ->and($booking->arrived_at)->toBeNull()
        ->and($booking->arrived_plate)->toBeNull()
        ->and($booking->plate_mismatch)->toBeFalse()
        ->and($booking->plate_verified_by)->toBeNull()
        ->and($booking->cancelled_at)->toBeNull()
        ->and($booking->cancel_reason)->toBeNull();
});

test('a change cannot smuggle a status or a dock in', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier, '2027-06-02 14:00');

    actingAs($supplier->user);

    patchJson("/api/v1/bookings/{$booking->id}", [
        'pallets' => 24,
        'status' => 'completed',
        'dock_id' => Dock::query()->where('number', 7)->firstOrFail()->id,
        'supplier_id' => Supplier::factory()->approved()->create()->id,
        'type' => 'outbound',
    ])->assertOk();

    $booking->refresh();

    expect($booking->pallets)->toBe(24)
        ->and($booking->status)->toBe(BookingStatus::Booked)
        ->and($booking->type)->toBe(DockUsage::Inbound)
        ->and($booking->supplier_id)->toBe($supplier->id)
        ->and($booking->dock->number)->toBe(1);
});

test('a supplier cannot approve themselves or change their role', function () {
    $supplier = Supplier::factory()->pending()->create();

    actingAs($supplier->user);

    patchJson('/api/v1/profile', [
        'name' => 'Jamie Berg',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1',
        'status' => 'approved',
        'role' => 'admin',
        'email' => 'admin@kidas.test',
        'user_id' => User::factory()->admin()->create()->id,
    ])->assertOk();

    $supplier->refresh();

    expect($supplier->status)->toBe(SupplierStatus::Pending)
        ->and($supplier->company_name)->toBe('Nordfracht GmbH')
        ->and($supplier->user->role)->toBe(UserRole::Supplier)
        ->and($supplier->user->email)->not->toBe('admin@kidas.test');
});

test('a registration cannot hand itself a role', function () {
    postJson('/api/v1/register', [
        'name' => 'Jamie Berg',
        'email' => 'dispo@nordfracht.test',
        'password' => 'correct horse battery',
        'password_confirmation' => 'correct horse battery',
        'company_name' => 'Nordfracht GmbH',
        'company_phone' => '+49 40 1',
        'role' => 'admin',
        'status' => 'approved',
    ])->assertCreated();

    $user = User::query()->where('email', 'dispo@nordfracht.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Supplier)
        ->and($user->supplier->status)->toBe(SupplierStatus::Pending);
});

test('a plate cannot be saved onto another company', function () {
    $supplier = Supplier::factory()->approved()->create();
    $stranger = Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    postJson('/api/v1/vehicles', [
        'license_plate' => 'HHAB1234',
        'supplier_id' => $stranger->id,
    ])->assertCreated();

    expect($supplier->vehicles()->count())->toBe(1)
        ->and($stranger->vehicles()->count())->toBe(0);
});

test('the warehouse cannot set a time of arrival by hand', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create(), '2027-06-02 06:00');

    travelTo(CarbonImmutable::parse('2027-06-02 06:05', 'Europe/Berlin'));

    actingAs(User::factory()->staff()->create());

    postJson("/api/v1/bookings/{$booking->id}/arrival", [
        'license_plate' => 'HHAB1234',
        'arrived_at' => '2027-06-01T06:00:00Z',
        'status' => 'completed',
    ])->assertOk();

    $booking->refresh();

    expect($booking->arrived_at->toIso8601ZuluString())->toBe('2027-06-02T04:05:00Z')
        ->and($booking->status)->toBe(BookingStatus::Arrived);
});
