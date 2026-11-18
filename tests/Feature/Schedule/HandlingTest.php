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
    travelTo(CarbonImmutable::parse('2027-06-02 12:00', 'Europe/Berlin'));
});

function arrivedBooking(bool $mismatch = false, ?User $verifiedBy = null): Booking
{
    arrivalDay();

    $booking = awaitedBooking();
    $booking->status = BookingStatus::Arrived;
    $booking->arrived_at = now();
    $booking->arrived_plate = $mismatch ? 'HHZZ9999' : 'HHAB1234';
    $booking->plate_mismatch = $mismatch;
    $booking->plate_verified_by = $verifiedBy?->id;
    $booking->save();

    return $booking;
}

test('starts the handling of a lorry that is waiting', function () {
    $booking = arrivedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/handling")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::InProgress)
        ->and($booking->started_at)->not->toBeNull()
        ->and($booking->completed_at)->toBeNull();
});

test('releases the dock when the handling is done', function () {
    $booking = arrivedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertOk();

    travelTo(CarbonImmutable::parse('2027-06-02 12:40', 'Europe/Berlin'));

    postJson("/api/v1/bookings/{$booking->id}/completion")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($booking->completed_at->toIso8601ZuluString())->toBe('2027-06-02T10:40:00Z')
        ->and($booking->started_at->toIso8601ZuluString())->toBe('2027-06-02T10:00:00Z');
});

test('writes both steps into the history', function () {
    $booking = arrivedBooking();
    $staff = asStaff();

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/completion")->assertOk();

    $events = $booking->events()->orderBy('id')->get()->map(
        fn ($event): array => [$event->from_status->value, $event->to_status->value, $event->note, $event->user_id]
    )->all();

    expect($events)->toBe([
        ['arrived', 'in_progress', 'Handling started', $staff->id],
        ['in_progress', 'completed', 'Dock released', $staff->id],
    ]);
});

test('refuses to start while the plate is in question', function () {
    $booking = arrivedBooking(mismatch: true);
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/handling")
        ->assertStatus(422)
        ->assertJsonPath('message', 'The plate has to be confirmed with the company before handling starts.');

    expect($booking->refresh()->status)->toBe(BookingStatus::Arrived)
        ->and($booking->started_at)->toBeNull();
});

test('starts once the plate was confirmed', function () {
    $staff = User::factory()->staff()->create();
    $booking = arrivedBooking(mismatch: true, verifiedBy: $staff);

    actingAs($staff);

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);
});

test('refuses a step that the booking cannot take', function (BookingStatus $status, string $path, string $message) {
    arrivalDay();

    $booking = awaitedBooking();
    $booking->status = $status;
    $booking->save();

    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/{$path}")
        ->assertStatus(422)
        ->assertJsonPath('message', $message);

    expect($booking->refresh()->status)->toBe($status);
})->with([
    'booked cannot start' => [BookingStatus::Booked, 'handling', 'A booking that is Booked cannot become In progress.'],
    'in progress cannot start again' => [BookingStatus::InProgress, 'handling', 'A booking that is In progress cannot become In progress.'],
    'completed cannot start' => [BookingStatus::Completed, 'handling', 'A booking that is Completed cannot become In progress.'],
    'cancelled cannot start' => [BookingStatus::Cancelled, 'handling', 'A booking that is Cancelled cannot become In progress.'],
    'no show cannot start' => [BookingStatus::NoShow, 'handling', 'A booking that is No show cannot become In progress.'],
    'arrived cannot be completed' => [BookingStatus::Arrived, 'completion', 'A booking that is Arrived cannot become Completed.'],
    'booked cannot be completed' => [BookingStatus::Booked, 'completion', 'A booking that is Booked cannot become Completed.'],
    'completed cannot be completed again' => [BookingStatus::Completed, 'completion', 'A booking that is Completed cannot become Completed.'],
]);

test('keeps the booking on the timeline while it is handled', function () {
    $booking = arrivedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertOk();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonPath('data.docks.0.bookings.0.status', 'in_progress')
        ->assertJsonPath('data.docks.0.bookings.0.started_at', '2027-06-02T10:00:00Z');
});

test('lets an admin handle a lorry as well', function () {
    $booking = arrivedBooking();

    actingAs(User::factory()->admin()->create());

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/completion")->assertOk();
});

test('keeps a supplier away from the handling', function () {
    $booking = arrivedBooking();

    actingAs(Supplier::factory()->approved()->create()->user);

    postJson("/api/v1/bookings/{$booking->id}/handling")->assertForbidden();
    postJson("/api/v1/bookings/{$booking->id}/completion")->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Arrived);
});

test('answers a guest with 401', function () {
    postJson('/api/v1/bookings/1/handling')->assertUnauthorized();
    postJson('/api/v1/bookings/1/completion')->assertUnauthorized();
});

test('answers 404 for a booking that does not exist', function () {
    asStaff();

    postJson('/api/v1/bookings/999/handling')->assertNotFound();
    postJson('/api/v1/bookings/999/completion')->assertNotFound();
});
