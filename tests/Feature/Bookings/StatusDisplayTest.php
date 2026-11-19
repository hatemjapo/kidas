<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Models\Booking;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

function bookingOnDockForStatus(Supplier $supplier, int $dockNumber, string $start, string $end, BookingStatus $status = BookingStatus::Booked): Booking
{
    $booking = new Booking;
    $booking->type = DockUsage::Inbound;
    $booking->dock_id = Dock::query()->where('number', $dockNumber)->firstOrFail()->id;
    $booking->supplier_id = $supplier->id;
    $booking->order_number = 'PO-4711';
    $booking->pallets = 10;
    $booking->license_plate = 'HHAB1234';
    $booking->driver_phone = '+49 170 1234567';
    $booking->starts_at = Carbon::parse($start, 'Europe/Berlin')->utc();
    $booking->ends_at = Carbon::parse($end, 'Europe/Berlin')->utc();
    $booking->status = $status;
    $booking->created_by = $supplier->user->id;
    $booking->save();

    return $booking;
}

test('stays quiet long before the slot', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.window_open', false)
        ->assertJsonPath('data.dock_free', false)
        ->assertJsonPath('data.poll_seconds', 30);
});

test('opens the window exactly fifteen minutes before the slot', function (string $now, bool $open) {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');

    travelTo(CarbonImmutable::parse($now, 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.window_open', $open);
})->with([
    'sixteen minutes before' => ['2027-06-02 09:44', false],
    'fifteen minutes before' => ['2027-06-02 09:45', true],
    'at the slot' => ['2027-06-02 10:00', true],
    'after the slot has started' => ['2027-06-02 10:20', true],
]);

test('says the dock is free when nobody is on it', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.window_open', true)
        ->assertJsonPath('data.dock_free', true)
        ->assertJsonPath('data.busy_until', null);
});

test('says the dock is taken while somebody else is on it', function (BookingStatus $status) {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');
    bookingOnDockForStatus(Supplier::factory()->approved()->create(), 1, '2027-06-02 09:30', '2027-06-02 10:00', $status);

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.dock_free', false)
        ->assertJsonPath('data.busy_until', '2027-06-02T08:00:00Z');
})->with([BookingStatus::Booked, BookingStatus::Arrived, BookingStatus::InProgress]);

test('frees the dock once the lorry before is done', function (BookingStatus $status) {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');
    bookingOnDockForStatus(Supplier::factory()->approved()->create(), 1, '2027-06-02 09:30', '2027-06-02 10:00', $status);

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.dock_free', true);
})->with([BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow]);

test('ignores a lorry on another dock', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');
    bookingOnDockForStatus(Supplier::factory()->approved()->create(), 2, '2027-06-02 09:30', '2027-06-02 10:00');

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")->assertOk()->assertJsonPath('data.dock_free', true);
});

test('does not hold the dock against itself', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');

    travelTo(CarbonImmutable::parse('2027-06-02 10:10', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")->assertOk()->assertJsonPath('data.dock_free', true);
});

test('ignores a lorry whose slot is already over', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30');
    bookingOnDockForStatus(Supplier::factory()->approved()->create(), 1, '2027-06-02 09:00', '2027-06-02 09:30');

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")->assertOk()->assertJsonPath('data.dock_free', true);
});

test('closes the window once the booking itself is over', function (BookingStatus $status) {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30', $status);

    travelTo(CarbonImmutable::parse('2027-06-02 09:50', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")
        ->assertOk()
        ->assertJsonPath('data.window_open', false);
})->with([BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow]);

test('keeps the window open while the lorry is being handled', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus($supplier, 1, '2027-06-02 10:00', '2027-06-02 10:30', BookingStatus::InProgress);

    travelTo(CarbonImmutable::parse('2027-06-02 10:10', 'Europe/Berlin'));

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")->assertOk()->assertJsonPath('data.window_open', true);
});

test('keeps the status of a foreign booking out of reach', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOnDockForStatus(Supplier::factory()->approved()->create(), 1, '2027-06-02 10:00', '2027-06-02 10:30');

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}/status")->assertNotFound();
});

test('answers the other roles with 403 and a guest with 401', function () {
    $booking = bookingOnDockForStatus(Supplier::factory()->approved()->create(), 1, '2027-06-02 10:00', '2027-06-02 10:30');

    getJson("/api/v1/bookings/{$booking->id}/status")->assertUnauthorized();

    foreach (['admin', 'staff'] as $role) {
        actingAs(User::factory()->{$role}()->create());

        getJson("/api/v1/bookings/{$booking->id}/status")->assertForbidden();
    }
});
