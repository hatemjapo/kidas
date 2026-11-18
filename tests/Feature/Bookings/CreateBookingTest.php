<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));
});

function bookableDay(string $date = '2027-06-02', DockUsage $usage = DockUsage::Inbound): CalendarDay
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => '2027-06-02T06:00:00+02:00',
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'HH-AB 1234',
    ], $overrides);
}

function bookAsSupplier(?Supplier $supplier = null): Supplier
{
    $supplier ??= Supplier::factory()->approved()->create();

    actingAs($supplier->user);

    return $supplier;
}

test('books a slot and answers with the dock', function () {
    bookableDay();
    $supplier = bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'booked')
        ->assertJsonPath('data.type', 'inbound')
        ->assertJsonPath('data.starts_at', '2027-06-02T04:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-06-02T04:15:00Z')
        ->assertJsonPath('data.license_plate', 'HHAB1234')
        ->assertJsonPath('data.dock_number', 1);

    $booking = Booking::query()->firstOrFail();

    expect($booking->supplier_id)->toBe($supplier->id)
        ->and($booking->created_by)->toBe($supplier->user->id)
        ->and($booking->status)->toBe(BookingStatus::Booked)
        ->and($booking->order_number)->toBe('PO-4711');
});

test('writes the first event of the booking', function () {
    bookableDay();
    $supplier = bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    $event = BookingEvent::query()->firstOrFail();

    expect($event->from_status)->toBeNull()
        ->and($event->to_status)->toBe(BookingStatus::Booked)
        ->and($event->user_id)->toBe($supplier->user->id);
});

test('takes the lowest free dock', function () {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertJsonPath('data.dock_number', 1);

    bookAsSupplier();
    postJson('/api/v1/bookings', bookingPayload())->assertJsonPath('data.dock_number', 2);

    bookAsSupplier();
    postJson('/api/v1/bookings', bookingPayload())->assertJsonPath('data.dock_number', 3);
});

test('answers 409 once every dock is taken', function () {
    bookableDay();

    foreach (range(1, 10) as $ignored) {
        bookAsSupplier();
        postJson('/api/v1/bookings', bookingPayload())->assertCreated();
    }

    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())
        ->assertStatus(409)
        ->assertJsonPath('message', 'This slot has just been taken. Please pick another one.');

    expect(Booking::query()->count())->toBe(10);
});

test('still offers the neighbouring start times when one dock is taken', function () {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    $times = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');

    expect($times)->toContain('2027-06-02T04:00:00Z')
        ->and($times)->toContain('2027-06-02T04:15:00Z');
});

test('stops offering a start time once all docks are taken', function () {
    bookableDay();

    foreach (range(1, 10) as $ignored) {
        bookAsSupplier();
        postJson('/api/v1/bookings', bookingPayload())->assertCreated();
    }

    bookAsSupplier();

    $times = array_column(getJson('/api/v1/slots?type=inbound&pallets=10')->json('data.0.slots'), 'starts_at');

    expect($times)->not->toContain('2027-06-02T04:00:00Z')
        ->and($times)->toContain('2027-06-02T04:15:00Z');
});

test('refuses a start time that is not on offer', function (string $startsAt) {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload(['starts_at' => $startsAt]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
})->with([
    'off the quarter hour' => ['2027-06-02T06:05:00+02:00'],
    'before the warehouse opens' => ['2027-06-02T04:00:00+02:00'],
    'after it closes' => ['2027-06-02T18:00:00+02:00'],
    'on a day that was never released' => ['2027-06-09T06:00:00+02:00'],
    'in the past' => ['2027-05-30T06:00:00+02:00'],
]);

test('refuses a booking that would cross the change of shift', function () {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload([
        'pallets' => 70,
        'starts_at' => '2027-06-02T13:00:00+02:00',
    ]))->assertStatus(422)->assertJsonValidationErrors('starts_at');
});

test('picks the plate from a saved vehicle', function () {
    bookableDay();
    $supplier = bookAsSupplier();
    $vehicle = Vehicle::factory()->for($supplier)->create(['license_plate' => 'HHZZ999']);

    postJson('/api/v1/bookings', bookingPayload(['license_plate' => null, 'vehicle_id' => $vehicle->id]))
        ->assertCreated()
        ->assertJsonPath('data.license_plate', 'HHZZ999');
});

test('remembers a plate that was typed for the first time', function () {
    bookableDay();
    $supplier = bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    expect($supplier->vehicles()->pluck('license_plate')->all())->toBe(['HHAB1234']);
});

test('does not save the same plate twice', function () {
    bookableDay();
    $supplier = bookAsSupplier();
    Vehicle::factory()->for($supplier)->create(['license_plate' => 'HHAB1234']);

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    expect($supplier->vehicles()->count())->toBe(1);
});

test('refuses a vehicle of another supplier', function () {
    bookableDay();
    bookAsSupplier();
    $foreign = Vehicle::factory()->create();

    postJson('/api/v1/bookings', bookingPayload(['license_plate' => null, 'vehicle_id' => $foreign->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('vehicle_id');
});

test('insists on a complete booking', function (array $overrides, string $field) {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload($overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no type' => [['type' => null], 'type'],
    'no pallets' => [['pallets' => null], 'pallets'],
    'too many pallets' => [['pallets' => 71], 'pallets'],
    'no start' => [['starts_at' => null], 'starts_at'],
    'no order number' => [['order_number' => ''], 'order_number'],
    'no phone' => [['driver_phone' => ''], 'driver_phone'],
    'neither vehicle nor plate' => [['license_plate' => null], 'vehicle_id'],
]);

test('separates deliveries from pickups', function () {
    bookableDay(usage: DockUsage::Outbound);
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertStatus(422);
    postJson('/api/v1/bookings', bookingPayload(['type' => 'outbound']))->assertCreated();
});

test('the database refuses two overlapping bookings on one dock', function () {
    bookableDay();
    $supplier = bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    $existing = Booking::query()->firstOrFail();

    $second = $existing->replicate();
    $second->order_number = 'PO-0815';

    expect(fn () => $second->save())->toThrow(QueryException::class);
});

test('a cancelled booking no longer blocks its dock', function () {
    bookableDay();
    bookAsSupplier();

    postJson('/api/v1/bookings', bookingPayload())->assertCreated();

    $existing = Booking::query()->firstOrFail();
    $existing->status = BookingStatus::Cancelled;
    $existing->save();

    $second = $existing->replicate();
    $second->status = BookingStatus::Booked;
    $second->order_number = 'PO-0815';

    expect(fn () => $second->save())->not->toThrow(QueryException::class);
});

test('answers a guest with 401', function () {
    postJson('/api/v1/bookings', bookingPayload())->assertUnauthorized();
});

test('keeps the other roles out of the bookings', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    postJson('/api/v1/bookings', bookingPayload())->assertForbidden();
})->with(['admin', 'staff']);
