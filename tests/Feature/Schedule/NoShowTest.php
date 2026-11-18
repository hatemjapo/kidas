<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 12:30', 'Europe/Berlin'));
});

function missedBooking(BookingStatus $status = BookingStatus::Booked): Booking
{
    arrivalDay();

    $booking = awaitedBooking();
    $booking->status = $status;
    $booking->save();

    return $booking;
}

test('marks a lorry that never turned up', function () {
    $booking = missedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', 'no_show');

    expect($booking->refresh()->status)->toBe(BookingStatus::NoShow);
});

test('writes the no-show into the history', function () {
    $booking = missedBooking();
    $staff = asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertOk();

    $event = $booking->events()->latest('id')->firstOrFail();

    expect($event->from_status)->toBe(BookingStatus::Booked)
        ->and($event->to_status)->toBe(BookingStatus::NoShow)
        ->and($event->note)->toBe('Nobody turned up')
        ->and($event->user_id)->toBe($staff->id);
});

test('waits until the slot is over', function (string $now) {
    $booking = missedBooking();

    travelTo(CarbonImmutable::parse($now, 'Europe/Berlin'));

    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")
        ->assertStatus(422)
        ->assertJsonPath('message', 'The slot is not over yet.');

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
})->with([
    'long before' => ['2027-06-02 08:00'],
    'while it runs' => ['2027-06-02 12:05'],
    'a minute before the end' => ['2027-06-02 12:14'],
]);

test('marks it the moment the slot has passed', function () {
    $booking = missedBooking();

    travelTo(CarbonImmutable::parse('2027-06-02 12:15', 'Europe/Berlin'));

    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::NoShow);
});

test('refuses a no-show for a lorry that was there', function (BookingStatus $status) {
    $booking = missedBooking($status);
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")
        ->assertStatus(422)
        ->assertJsonPath('message', "A booking that is {$status->label()} cannot become No show.");

    expect($booking->refresh()->status)->toBe($status);
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('frees the dock for somebody else', function () {
    $booking = missedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertOk();

    $another = new Booking;
    $another->type = $booking->type;
    $another->dock_id = $booking->dock_id;
    $another->supplier_id = $booking->supplier_id;
    $another->order_number = 'PO-9999';
    $another->pallets = 4;
    $another->license_plate = 'HHCD5678';
    $another->driver_phone = '+49 170 7654321';
    $another->starts_at = $booking->starts_at;
    $another->ends_at = $booking->ends_at;
    $another->status = BookingStatus::Booked;
    $another->created_by = $booking->created_by;

    expect(fn () => $another->save())->not->toThrow(Exception::class);
});

test('keeps the no-show on the timeline', function () {
    $booking = missedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertOk();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonPath('data.docks.0.bookings.0.status', 'no_show');
});

test('lets an admin mark a no-show as well', function () {
    $booking = missedBooking();

    actingAs(User::factory()->admin()->create());

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertOk();
});

test('keeps a supplier away from the no-show', function () {
    $booking = missedBooking();

    actingAs(Supplier::factory()->approved()->create()->user);

    postJson("/api/v1/bookings/{$booking->id}/no-show")->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
});

test('answers a guest with 401', function () {
    postJson('/api/v1/bookings/1/no-show')->assertUnauthorized();
});

test('answers 404 for a booking that does not exist', function () {
    asStaff();

    postJson('/api/v1/bookings/999/no-show')->assertNotFound();
});
