<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));
});

function bookingFor(Supplier $supplier, string $startsAt, BookingStatus $status = BookingStatus::Booked): Booking
{
    $booking = new Booking;
    $booking->type = DockUsage::Inbound;
    $booking->dock_id = Dock::query()->orderBy('number')->firstOrFail()->id;
    $booking->supplier_id = $supplier->id;
    $booking->order_number = 'PO-'.substr($startsAt, 11, 5);
    $booking->pallets = 10;
    $booking->license_plate = 'HHAB1234';
    $booking->driver_phone = '+49 170 1234567';
    $booking->starts_at = Carbon::parse($startsAt, 'Europe/Berlin')->utc();
    $booking->ends_at = Carbon::parse($startsAt, 'Europe/Berlin')->addMinutes(15)->utc();
    $booking->status = $status;
    $booking->created_by = $supplier->user->id;
    $booking->save();

    $event = new BookingEvent;
    $event->booking_id = $booking->id;
    $event->user_id = $supplier->user->id;
    $event->to_status = BookingStatus::Booked;
    $event->created_at = now();
    $event->save();

    return $booking;
}

test('lists only the own bookings', function () {
    $supplier = Supplier::factory()->approved()->create();
    bookingFor($supplier, '2027-06-02 06:00');
    bookingFor(Supplier::factory()->approved()->create(), '2027-06-02 07:00');

    actingAs($supplier->user);

    getJson('/api/v1/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', 'PO-06:00');
});

test('puts the latest booking first', function () {
    $supplier = Supplier::factory()->approved()->create();
    bookingFor($supplier, '2027-06-02 06:00');
    bookingFor($supplier, '2027-06-04 06:00');
    bookingFor($supplier, '2027-06-03 06:00');

    actingAs($supplier->user);

    $dates = array_column(getJson('/api/v1/bookings')->assertOk()->json('data'), 'starts_at');

    expect($dates)->toBe([
        '2027-06-04T04:00:00Z',
        '2027-06-03T04:00:00Z',
        '2027-06-02T04:00:00Z',
    ]);
});

test('hands out the pages one by one', function () {
    $supplier = Supplier::factory()->approved()->create();

    foreach (range(1, 25) as $minute) {
        bookingFor($supplier, CarbonImmutable::parse('2027-06-02 06:00')->addMinutes($minute * 30)->format('Y-m-d H:i'));
    }

    actingAs($supplier->user);

    $first = getJson('/api/v1/bookings')->assertOk();
    $second = getJson('/api/v1/bookings?page=2')->assertOk();

    expect($first->json('data'))->toHaveCount(20)
        ->and($second->json('data'))->toHaveCount(5)
        ->and($first->json('meta.total'))->toBe(25)
        ->and(array_intersect(
            array_column($first->json('data'), 'id'),
            array_column($second->json('data'), 'id'),
        ))->toBe([]);
});

test('says so when there is nothing booked yet', function () {
    actingAs(Supplier::factory()->approved()->create()->user);

    getJson('/api/v1/bookings')->assertOk()->assertJsonCount(0, 'data');
});

test('shows one booking with its dock and its history', function () {
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingFor($supplier, '2027-06-02 06:00');

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'booked')
        ->assertJsonPath('data.dock_number', 1)
        ->assertJsonPath('data.pallets', 10)
        ->assertJsonCount(1, 'data.history')
        ->assertJsonPath('data.history.0.from_status', null)
        ->assertJsonPath('data.history.0.to_status', 'booked')
        ->assertJsonPath('data.history.0.by', $supplier->user->name);
});

test('leaves the history out of the list', function () {
    $supplier = Supplier::factory()->approved()->create();
    bookingFor($supplier, '2027-06-02 06:00');

    actingAs($supplier->user);

    getJson('/api/v1/bookings')->assertOk()->assertJsonMissingPath('data.0.history');
});

test('answers 404 for a booking of another supplier', function () {
    $supplier = Supplier::factory()->approved()->create();
    $foreign = bookingFor(Supplier::factory()->approved()->create(), '2027-06-02 06:00');

    actingAs($supplier->user);

    getJson("/api/v1/bookings/{$foreign->id}")->assertNotFound();

    expect(Booking::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('answers 404 for a booking that does not exist', function () {
    actingAs(Supplier::factory()->approved()->create()->user);

    getJson('/api/v1/bookings/999')->assertNotFound();
});

test('keeps the other roles out of the bookings', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/bookings')->assertForbidden();
    getJson('/api/v1/bookings/1')->assertForbidden();
})->with(['admin', 'staff']);

test('answers a guest with 401', function () {
    getJson('/api/v1/bookings')->assertUnauthorized();
    getJson('/api/v1/bookings/1')->assertUnauthorized();
});
