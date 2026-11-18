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
    travelTo(CarbonImmutable::parse('2027-06-02 08:00', 'Europe/Berlin'));
});

function arrivalDay(string $date = '2027-06-02'): CalendarDay
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

function awaitedBooking(string $plate = 'HHAB1234', BookingStatus $status = BookingStatus::Booked): Booking
{
    $supplier = Supplier::factory()->approved()->create(['company_name' => 'Nordfracht GmbH']);

    $booking = new Booking;
    $booking->type = DockUsage::Inbound;
    $booking->dock_id = Dock::query()->where('number', 1)->firstOrFail()->id;
    $booking->supplier_id = $supplier->id;
    $booking->order_number = 'PO-4711';
    $booking->pallets = 10;
    $booking->license_plate = $plate;
    $booking->driver_phone = '+49 170 1234567';
    $booking->starts_at = Carbon::parse('2027-06-02 12:00', 'Europe/Berlin')->utc();
    $booking->ends_at = Carbon::parse('2027-06-02 12:15', 'Europe/Berlin')->utc();
    $booking->status = $status;
    $booking->created_by = $supplier->user->id;
    $booking->save();

    return $booking;
}

function asStaff(): User
{
    $user = User::factory()->staff()->create();

    actingAs($user);

    return $user;
}

test('registers an arrival with the plate that came', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'hh-ab 1234'])
        ->assertOk()
        ->assertJsonPath('data.status', 'arrived')
        ->assertJsonPath('data.arrived_plate', 'HHAB1234')
        ->assertJsonPath('data.plate_mismatch', false);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Arrived)
        ->and($booking->arrived_at)->not->toBeNull()
        ->and($booking->arrived_plate)->toBe('HHAB1234')
        ->and($booking->plate_mismatch)->toBeFalse();
});

test('flags an arrival with a different plate', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HH-ZZ 9999'])
        ->assertOk()
        ->assertJsonPath('data.status', 'arrived')
        ->assertJsonPath('data.arrived_plate', 'HHZZ9999')
        ->assertJsonPath('data.plate_mismatch', true)
        ->assertJsonPath('data.plate_verified', false);

    expect($booking->refresh()->plate_mismatch)->toBeTrue()
        ->and($booking->status)->toBe(BookingStatus::Arrived)
        ->and($booking->plate_verified_by)->toBeNull();
});

test('a plate in another spelling is no mismatch', function (string $typed) {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => $typed])
        ->assertOk()
        ->assertJsonPath('data.plate_mismatch', false);
})->with([
    'with a hyphen' => ['HH-AB 1234'],
    'in lower case' => ['hhab1234'],
    'with dots' => ['HH.AB.1234'],
]);

test('writes the arrival into the history', function () {
    arrivalDay();
    $booking = awaitedBooking();
    $staff = asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();

    $event = $booking->events()->latest('id')->firstOrFail();

    expect($event->from_status)->toBe(BookingStatus::Booked)
        ->and($event->to_status)->toBe(BookingStatus::Arrived)
        ->and($event->user_id)->toBe($staff->id)
        ->and($event->note)->toBe('Arrived with HHZZ9999 instead of HHAB1234');
});

test('refuses an arrival for a booking in another state', function (BookingStatus $status) {
    arrivalDay();
    $booking = awaitedBooking(status: $status);
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only a booking that is still awaited can be registered as arrived.');

    expect($booking->refresh()->arrived_at)->toBeNull();
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('insists on a plate', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('license_plate');
});

test('confirms a plate after checking with the company', function () {
    arrivalDay();
    $booking = awaitedBooking();
    $staff = asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();

    postJson("/api/v1/bookings/{$booking->id}/plate-verification", ['note' => 'Rang the office, same lorry.'])
        ->assertOk()
        ->assertJsonPath('data.plate_mismatch', true)
        ->assertJsonPath('data.plate_verified', true);

    $booking->refresh();

    expect($booking->plate_verified_by)->toBe($staff->id)
        ->and($booking->plate_mismatch)->toBeTrue()
        ->and($booking->events()->latest('id')->firstOrFail()->note)->toBe('Rang the office, same lorry.');
});

test('confirms a plate without a note', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/plate-verification")->assertOk();

    expect($booking->refresh()->events()->latest('id')->firstOrFail()->note)
        ->toBe('Plate confirmed with the company');
});

test('refuses to confirm when there is nothing in question', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertOk();

    postJson("/api/v1/bookings/{$booking->id}/plate-verification")
        ->assertForbidden()
        ->assertJsonPath('message', 'This booking has no open plate mismatch.');
});

test('refuses to confirm the same mismatch twice', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/plate-verification")->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/plate-verification")->assertForbidden();
});

test('shows the arrival on the timeline', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonPath('data.docks.0.bookings.0.status', 'arrived')
        ->assertJsonPath('data.docks.0.bookings.0.arrived_plate', 'HHZZ9999')
        ->assertJsonPath('data.docks.0.bookings.0.plate_mismatch', true);
});

test('lets an admin register an arrival as well', function () {
    arrivalDay();
    $booking = awaitedBooking();

    actingAs(User::factory()->admin()->create());

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertOk();
});

test('keeps a supplier away from the arrival', function () {
    arrivalDay();
    $booking = awaitedBooking();

    actingAs(Supplier::factory()->approved()->create()->user);

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertForbidden();
    postJson("/api/v1/bookings/{$booking->id}/plate-verification")->assertForbidden();

    expect($booking->refresh()->arrived_at)->toBeNull();
});

test('answers a guest with 401', function () {
    postJson('/api/v1/bookings/1/arrival', ['license_plate' => 'HHAB1234'])->assertUnauthorized();
    postJson('/api/v1/bookings/1/plate-verification')->assertUnauthorized();
});

test('answers 404 for a booking that does not exist', function () {
    asStaff();

    postJson('/api/v1/bookings/999/arrival', ['license_plate' => 'HHAB1234'])->assertNotFound();
});

test('corrects a plate that was typed wrongly', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1243'])
        ->assertOk()
        ->assertJsonPath('data.plate_mismatch', true);

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'hh-ab 1234'])
        ->assertOk()
        ->assertJsonPath('data.arrived_plate', 'HHAB1234')
        ->assertJsonPath('data.plate_mismatch', false);

    expect($booking->refresh()->plate_mismatch)->toBeFalse()
        ->and($booking->arrived_plate)->toBe('HHAB1234')
        ->and($booking->status)->toBe(BookingStatus::Arrived);
});

test('keeps the flag when the correction still differs', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ8888'])
        ->assertOk()
        ->assertJsonPath('data.arrived_plate', 'HHZZ8888')
        ->assertJsonPath('data.plate_mismatch', true);
});

test('withdraws a confirmation that was given for the old plate', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ9999'])->assertOk();
    postJson("/api/v1/bookings/{$booking->id}/plate-verification")->assertOk();

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHZZ8888'])
        ->assertOk()
        ->assertJsonPath('data.plate_verified', false);

    expect($booking->refresh()->plate_verified_by)->toBeNull();
});

test('writes the correction into the history', function () {
    arrivalDay();
    $booking = awaitedBooking();
    $staff = asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1243'])->assertOk();
    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertOk();

    $event = $booking->events()->latest('id')->firstOrFail();

    expect($event->note)->toBe('Plate corrected to HHAB1234')
        ->and($event->from_status)->toBe(BookingStatus::Arrived)
        ->and($event->to_status)->toBe(BookingStatus::Arrived)
        ->and($event->user_id)->toBe($staff->id);
});

test('keeps the time of arrival when the plate is corrected', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1243'])->assertOk();

    $arrivedAt = $booking->refresh()->arrived_at;

    travelTo(CarbonImmutable::parse('2027-06-02 09:30', 'Europe/Berlin'));

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertOk();

    expect($booking->refresh()->arrived_at->toIso8601ZuluString())->toBe($arrivedAt->toIso8601ZuluString());
});

test('refuses a correction for a lorry that is not waiting', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only the plate of a lorry that is waiting can be corrected.');

    expect($booking->refresh()->arrived_plate)->toBeNull();
});

test('insists on a plate for the correction', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1243'])->assertOk();

    patchJson("/api/v1/bookings/{$booking->id}/arrival", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('license_plate');
});

test('keeps a supplier away from the correction', function () {
    arrivalDay();
    $booking = awaitedBooking();
    asStaff();

    postJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1243'])->assertOk();

    actingAs(Supplier::factory()->approved()->create()->user);

    patchJson("/api/v1/bookings/{$booking->id}/arrival", ['license_plate' => 'HHAB1234'])->assertForbidden();

    expect($booking->refresh()->arrived_plate)->toBe('HHAB1243');
});
