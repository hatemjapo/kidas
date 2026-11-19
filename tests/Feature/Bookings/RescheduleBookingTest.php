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
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));
});

function openDayForReschedule(string $date, DockUsage $usage = DockUsage::Inbound): CalendarDay
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
            $row->usage = $usage;
            $row->save();
        }
    }

    return $day;
}

function bookedSlot(Supplier $supplier, string $startsAt = '2027-06-02T06:00:00+02:00', int $pallets = 10): Booking
{
    actingAs($supplier->user);

    postJson('/api/v1/bookings', [
        'type' => 'inbound',
        'pallets' => $pallets,
        'starts_at' => $startsAt,
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'HHAB1234',
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail();
}

test('moves a booking to another time', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])
        ->assertOk()
        ->assertJsonPath('data.starts_at', '2027-06-02T08:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-06-02T08:15:00Z');

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T08:00:00Z');
});

test('moves a booking into a window that overlaps its own', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();

    $booking = bookedSlot($supplier, '2027-06-02T06:00:00+02:00', pallets: 30);

    foreach (range(1, 9) as $ignored) {
        bookedSlot(Supplier::factory()->approved()->create(), '2027-06-02T06:15:00+02:00', pallets: 30);
    }

    actingAs($supplier->user);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T06:15:00+02:00'])
        ->assertOk()
        ->assertJsonPath('data.dock_number', 1);

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T04:15:00Z');
});

test('keeps its dock when only the pallet count grows', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['pallets' => 70])
        ->assertOk()
        ->assertJsonPath('data.dock_number', 1);
});

test('moves a booking to another day', function () {
    openDayForReschedule('2027-06-02');
    openDayForReschedule('2027-06-04');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-04T07:00:00+02:00'])
        ->assertOk()
        ->assertJsonPath('data.starts_at', '2027-06-04T05:00:00Z');
});

test('a new pallet count stretches the booking', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['pallets' => 70])
        ->assertOk()
        ->assertJsonPath('data.pallets', 70)
        ->assertJsonPath('data.starts_at', '2027-06-02T04:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-06-02T05:45:00Z');
});

test('gives the old slot back and takes the new one', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    foreach (range(1, 9) as $ignored) {
        bookedSlot(Supplier::factory()->approved()->create());
    }

    actingAs($supplier->user);

    $before = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');
    expect($before)->not->toContain('2027-06-02T04:00:00Z');

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])->assertOk();

    $after = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');
    expect($after)->toContain('2027-06-02T04:00:00Z');
});

test('answers 409 when no dock is free at the new time', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    foreach (range(1, 10) as $ignored) {
        bookedSlot(Supplier::factory()->approved()->create(), '2027-06-02T10:00:00+02:00');
    }

    actingAs($supplier->user);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])
        ->assertStatus(409);

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T04:00:00Z');
});

test('allows a move three hours and a minute before the slot', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier, '2027-06-02T12:00:00+02:00');

    travelTo(CarbonImmutable::parse('2027-06-02 08:59', 'Europe/Berlin'));

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T13:00:00+02:00'])->assertOk();
});

test('refuses a move less than three hours before the slot', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier, '2027-06-02T12:00:00+02:00');

    travelTo(CarbonImmutable::parse('2027-06-02 09:01', 'Europe/Berlin'));

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T13:00:00+02:00'])
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking can only be changed up to three hours before its slot.');

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T10:00:00Z');
});

test('refuses to move a booking that is no longer merely booked', function (BookingStatus $status) {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    $booking->status = $status;
    $booking->save();

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking in this state can no longer be changed.');
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('refuses a new start time that is not on offer', function (string $startsAt) {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => $startsAt])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');
})->with([
    'off the quarter hour' => ['2027-06-02T10:05:00+02:00'],
    'before the warehouse opens' => ['2027-06-02T04:00:00+02:00'],
    'on a day that was never released' => ['2027-06-09T10:00:00+02:00'],
]);

test('insists on something to change', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['starts_at', 'pallets']);
});

test('writes the move into the history', function () {
    openDayForReschedule('2027-06-02');
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookedSlot($supplier);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])->assertOk();

    $history = getJson("/api/v1/bookings/{$booking->id}")->json('data.history');

    expect($history)->toHaveCount(2)
        ->and($history[1]['note'])->toBe('Moved from 02.06.2027 06:00 to 02.06.2027 10:00')
        ->and($history[1]['from_status'])->toBe('booked')
        ->and($history[1]['to_status'])->toBe('booked');
});

test('answers 404 for a booking of another supplier', function () {
    openDayForReschedule('2027-06-02');
    $foreign = bookedSlot(Supplier::factory()->approved()->create());

    actingAs(Supplier::factory()->approved()->create()->user);

    patchJson("/api/v1/bookings/{$foreign->id}", ['starts_at' => '2027-06-02T10:00:00+02:00'])
        ->assertNotFound();

    expect($foreign->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T04:00:00Z');
});

test('keeps the other roles out of the reschedule', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    patchJson('/api/v1/bookings/1', ['starts_at' => '2027-06-02T10:00:00+02:00'])->assertForbidden();
})->with(['admin', 'staff']);

test('answers a guest with 401', function () {
    patchJson('/api/v1/bookings/1', ['starts_at' => '2027-06-02T10:00:00+02:00'])->assertUnauthorized();
});
