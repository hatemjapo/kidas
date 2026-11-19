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
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));
});

function bookingOfSupplier(Supplier $supplier, string $start = '2027-06-02 10:00', int $pallets = 10): Booking
{
    $booking = new Booking;
    $booking->type = DockUsage::Inbound;
    $booking->dock_id = Dock::query()->where('number', 1)->firstOrFail()->id;
    $booking->supplier_id = $supplier->id;
    $booking->order_number = 'PO-4711';
    $booking->pallets = $pallets;
    $booking->license_plate = 'HHAB1234';
    $booking->driver_phone = '+49 170 1234567';
    $booking->starts_at = Carbon::parse($start, 'Europe/Berlin')->utc();
    $booking->ends_at = Carbon::parse($start, 'Europe/Berlin')->addMinutes(30)->utc();
    $booking->status = BookingStatus::Booked;
    $booking->created_by = $supplier->user->id;
    $booking->save();

    return $booking;
}

function asAdmin(): User
{
    $user = User::factory()->admin()->create();

    actingAs($user);

    return $user;
}

test('moves a booking of any supplier', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertOk()
        ->assertJsonPath('data.starts_at', '2027-06-02T12:00:00Z');

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T12:00:00Z');
});

test('changes the pallets without touching the time', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['pallets' => 40])
        ->assertOk()
        ->assertJsonPath('data.pallets', 40)
        ->assertJsonPath('data.starts_at', '2027-06-02T08:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-06-02T09:00:00Z');

    expect($booking->refresh()->pallets)->toBe(40);
});

test('changes time and pallets at once', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", [
        'starts_at' => '2027-06-02T14:00:00+02:00',
        'pallets' => 24,
    ])->assertOk();

    $booking->refresh();

    expect($booking->pallets)->toBe(24)
        ->and($booking->starts_at->toIso8601ZuluString())->toBe('2027-06-02T12:00:00Z')
        ->and($booking->ends_at->toIso8601ZuluString())->toBe('2027-06-02T12:45:00Z');
});

test('moves without the three hours of distance', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T06:00:00+02:00'])
        ->assertOk();

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T04:00:00Z');
});

test('the supplier is held to the deadline at that same moment', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    actingAs($supplier->user);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T06:00:00+02:00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');
});

test('keeps a reason in the history', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $admin = asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", [
        'starts_at' => '2027-06-02T14:00:00+02:00',
        'reason' => 'Dock maintenance in the morning',
    ])->assertOk();

    $event = $booking->events()->latest('id')->firstOrFail();

    expect($event->note)->toBe('Moved from 02.06.2027 10:00 to 02.06.2027 14:00 (Dock maintenance in the morning)')
        ->and($event->user_id)->toBe($admin->id)
        ->and($event->from_status)->toBe(BookingStatus::Booked)
        ->and($event->to_status)->toBe(BookingStatus::Booked);
});

test('works without a reason', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertOk();

    expect($booking->refresh()->events()->latest('id')->firstOrFail()->note)
        ->toBe('Moved from 02.06.2027 10:00 to 02.06.2027 14:00');
});

test('insists on something to change', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['reason' => 'Just because'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['starts_at', 'pallets']);
});

test('frees the old slot for somebody else', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $other = Supplier::factory()->approved()->create();

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertOk();

    postJson('/api/v1/admin/bookings', adminPayload($other, [
        'starts_at' => '2027-06-02T10:00:00+02:00',
        'pallets' => 10,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.dock_number', 1);
});

test('keeps a day that was never released shut', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-03T10:00:00+02:00'])
        ->assertStatus(422)
        ->assertJsonPath('errors.starts_at.0', 'This day is not open for booking.');

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T08:00:00Z');
});

test('answers 409 once every dock is taken at that time', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    foreach (range(1, 10) as $ignored) {
        postJson('/api/v1/admin/bookings', adminPayload(Supplier::factory()->approved()->create(), [
            'starts_at' => '2027-06-02T14:00:00+02:00',
            'pallets' => 10,
        ]))->assertCreated();
    }

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertStatus(409);

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T08:00:00Z');
});

test('refuses to change a booking that is no longer merely booked', function (BookingStatus $status) {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());
    $booking->status = $status;
    $booking->save();

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertForbidden()
        ->assertJsonPath('message', 'A booking in this state can no longer be changed.');

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T08:00:00Z');
})->with([
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::Cancelled,
    BookingStatus::NoShow,
]);

test('keeps the dock type', function () {
    openedDay(DockUsage::Outbound, '2027-06-03');
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-03T10:00:00+02:00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');
});

test('keeps everybody else out of the admin change', function (string $state) {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    actingAs($state === 'supplier' ? $supplier->user : User::factory()->staff()->create());

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertForbidden();

    expect($booking->refresh()->starts_at->toIso8601ZuluString())->toBe('2027-06-02T08:00:00Z');
})->with(['supplier', 'staff']);

test('answers a guest with 401', function () {
    patchJson('/api/v1/admin/bookings/1', ['pallets' => 10])->assertUnauthorized();
});

test('answers 404 for a booking that does not exist', function () {
    asAdmin();

    patchJson('/api/v1/admin/bookings/999', ['pallets' => 10])->assertNotFound();
});
