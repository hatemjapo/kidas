<?php

use App\Enums\BookingStatus;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

test('cancels a booking of any supplier', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel", ['reason' => 'Dock out of service'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancel_reason)->toBe('Dock out of service')
        ->and($booking->cancelled_at)->not->toBeNull();
});

test('cancels without a reason', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertOk();

    expect($booking->refresh()->cancel_reason)->toBeNull();
});

test('writes the cancellation into the history', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $admin = asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel", ['reason' => 'Dock out of service'])->assertOk();

    $event = $booking->events()->latest('id')->firstOrFail();

    expect($event->from_status)->toBe(BookingStatus::Booked)
        ->and($event->to_status)->toBe(BookingStatus::Cancelled)
        ->and($event->note)->toBe('Dock out of service')
        ->and($event->user_id)->toBe($admin->id);
});

test('cancels inside the three hours the supplier is bound by', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier, '2027-06-02 07:00');

    actingAs($supplier->user);
    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertForbidden();

    asAdmin();
    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

test('frees the slot for somebody else', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $other = Supplier::factory()->approved()->create();

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertOk();

    postJson('/api/v1/admin/bookings', adminPayload($other, [
        'starts_at' => '2027-06-02T10:00:00+02:00',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.dock_number', 1);
});

test('refuses to cancel a booking that is no longer merely booked', function (BookingStatus $status) {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $booking->status = $status;
    $booking->save();

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking in this state can no longer be cancelled.');

    expect($booking->refresh()->status)->toBe($status);
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('refuses a reason that is far too long', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel", ['reason' => str_repeat('a', 501)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
});

test('keeps everybody else out of the admin cancellation', function (string $state) {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    actingAs($state === 'supplier' ? $supplier->user : User::factory()->staff()->create());

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
})->with(['supplier', 'staff']);

test('answers a guest with 401', function () {
    postJson('/api/v1/admin/bookings/1/cancel')->assertUnauthorized();
});

test('answers 404 for a booking that does not exist', function () {
    asAdmin();

    postJson('/api/v1/admin/bookings/999/cancel')->assertNotFound();
});

test('leaves the cancelled booking off the timeline', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertOk();

    Pest\Laravel\getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonCount(0, 'data.docks.0.bookings');
});
