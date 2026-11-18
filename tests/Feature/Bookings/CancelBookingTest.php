<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));
});

function openDayForCancel(string $date = '2027-06-02'): CalendarDay
{
    $day = new CalendarDay;
    $day->date = Carbon::parse($date);
    $day->is_released = true;
    $day->is_closed = false;
    $day->opens_at = '06:00:00';
    $day->closes_at = '18:00:00';
    $day->early_start = '06:00:00';
    $day->early_end = '14:00:00';
    $day->late_start = '14:00:00';
    $day->late_end = '18:00:00';
    $day->save();

    foreach (Dock::query()->get() as $dock) {
        foreach (Shift::cases() as $shift) {
            $row = new DockDayUsage;
            $row->calendar_day_id = $day->id;
            $row->dock_id = $dock->id;
            $row->shift = $shift;
            $row->usage = DockUsage::Inbound;
            $row->save();
        }
    }

    return $day;
}

function bookSlotFor(Supplier $supplier, string $startsAt = '2027-06-02T06:00:00+02:00'): Booking
{
    actingAs($supplier->user);

    postJson('/api/v1/bookings', [
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => $startsAt,
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'HHAB1234',
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail();
}

test('cancels a booking with a reason', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'The lorry broke down.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'The lorry broke down.');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->cancel_reason)->toBe('The lorry broke down.');
});

test('cancels a booking without a reason', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', null);

    expect($booking->refresh()->cancel_reason)->toBeNull();
});

test('writes the cancellation into the history', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'No driver.'])->assertOk();

    $history = getJson("/api/v1/bookings/{$booking->id}")->json('data.history');

    expect($history)->toHaveCount(2)
        ->and($history[1]['from_status'])->toBe('booked')
        ->and($history[1]['to_status'])->toBe('cancelled')
        ->and($history[1]['note'])->toBe('No driver.');
});

test('gives the slot back to everybody else', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    foreach (range(1, 9) as $ignored) {
        bookSlotFor(Supplier::factory()->approved()->create());
    }

    actingAs($supplier->user);

    $before = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');
    expect($before)->not->toContain('2027-06-02T04:00:00Z');

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

    $after = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');
    expect($after)->toContain('2027-06-02T04:00:00Z');
});

test('lets somebody else take the cancelled slot', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    foreach (range(1, 9) as $ignored) {
        bookSlotFor(Supplier::factory()->approved()->create());
    }

    actingAs($supplier->user);
    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

    $next = bookSlotFor(Supplier::factory()->approved()->create());

    expect($next->dock_id)->toBe($booking->dock_id);
});

test('keeps the cancelled booking in the list', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

    getJson('/api/v1/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'cancelled');
});

test('allows a cancellation three hours and a minute before the slot', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier, '2027-06-02T12:00:00+02:00');

    travelTo(CarbonImmutable::parse('2027-06-02 08:59', 'Europe/Berlin'));

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();
});

test('refuses a cancellation less than three hours before the slot', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier, '2027-06-02T12:00:00+02:00');

    travelTo(CarbonImmutable::parse('2027-06-02 09:01', 'Europe/Berlin'));

    postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking can only be cancelled up to three hours before its slot.');

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
});

test('refuses to cancel a booking that is no longer merely booked', function (BookingStatus $status) {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    $booking->status = $status;
    $booking->save();

    postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking in this state can no longer be cancelled.');
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('refuses to cancel twice', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertForbidden();

    expect($booking->refresh()->events()->count())->toBe(2);
});

test('refuses a reason that fills a book', function () {
    openDayForCancel();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookSlotFor($supplier);

    postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => str_repeat('a', 501)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($booking->refresh()->status)->toBe(BookingStatus::Booked);
});

test('answers 404 for a booking of another supplier', function () {
    openDayForCancel();
    $foreign = bookSlotFor(Supplier::factory()->approved()->create());

    actingAs(Supplier::factory()->approved()->create()->user);

    postJson("/api/v1/bookings/{$foreign->id}/cancel")->assertNotFound();

    expect($foreign->refresh()->status)->toBe(BookingStatus::Booked);
});

test('answers 404 for a booking that does not exist', function () {
    actingAs(Supplier::factory()->approved()->create()->user);

    postJson('/api/v1/bookings/999/cancel')->assertNotFound();
});

test('keeps the other roles out of the cancellation', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    postJson('/api/v1/bookings/1/cancel')->assertForbidden();
})->with(['admin', 'staff']);

test('answers a guest with 401', function () {
    postJson('/api/v1/bookings/1/cancel')->assertUnauthorized();
});
