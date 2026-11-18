<?php

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\OpeningDefault;
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

function scheduleDay(string $date = '2027-06-02', bool $released = true): CalendarDay
{
    $day = new CalendarDay;
    $day->date = Carbon::parse($date);
    $day->is_released = $released;
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
            $row->usage = $shift === Shift::Early ? DockUsage::Inbound : DockUsage::Outbound;
            $row->save();
        }
    }

    return $day;
}

function bookingOnDock(int $dockNumber, string $startsAt, BookingStatus $status = BookingStatus::Booked, ?Supplier $supplier = null): Booking
{
    $supplier ??= Supplier::factory()->approved()->create(['company_name' => 'Nordfracht GmbH']);

    $booking = new Booking;
    $booking->type = DockUsage::Inbound;
    $booking->dock_id = Dock::query()->where('number', $dockNumber)->firstOrFail()->id;
    $booking->supplier_id = $supplier->id;
    $booking->order_number = 'PO-4711';
    $booking->pallets = 24;
    $booking->license_plate = 'HHAB1234';
    $booking->driver_phone = '+49 170 1234567';
    $booking->starts_at = Carbon::parse($startsAt, 'Europe/Berlin')->utc();
    $booking->ends_at = Carbon::parse($startsAt, 'Europe/Berlin')->addMinutes(45)->utc();
    $booking->status = $status;
    $booking->created_by = $supplier->user->id;
    $booking->save();

    return $booking;
}

function asWarehouse(): User
{
    $user = User::factory()->staff()->create();

    actingAs($user);

    return $user;
}

test('answers with the day, its window and every dock', function () {
    scheduleDay();
    asWarehouse();

    $response = getJson('/api/v1/schedule?date=2027-06-02')->assertOk();

    $response->assertJsonPath('data.date', '2027-06-02')
        ->assertJsonPath('data.is_released', true)
        ->assertJsonPath('data.is_closed', false)
        ->assertJsonPath('data.opens_at', '2027-06-02T04:00:00Z')
        ->assertJsonPath('data.closes_at', '2027-06-02T16:00:00Z')
        ->assertJsonCount(10, 'data.docks')
        ->assertJsonPath('data.docks.0.number', 1)
        ->assertJsonPath('data.docks.9.number', 10);
});

test('tells what each dock is used for in each shift', function () {
    scheduleDay();
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonPath('data.docks.0.usage.early', 'inbound')
        ->assertJsonPath('data.docks.0.usage.late', 'outbound');
});

test('puts a booking on its own dock, with the company behind it', function () {
    scheduleDay();
    bookingOnDock(3, '2027-06-02 09:00');
    asWarehouse();

    $response = getJson('/api/v1/schedule?date=2027-06-02')->assertOk();

    $response->assertJsonCount(0, 'data.docks.0.bookings')
        ->assertJsonCount(1, 'data.docks.2.bookings')
        ->assertJsonPath('data.docks.2.bookings.0.starts_at', '2027-06-02T07:00:00Z')
        ->assertJsonPath('data.docks.2.bookings.0.ends_at', '2027-06-02T07:45:00Z')
        ->assertJsonPath('data.docks.2.bookings.0.company', 'Nordfracht GmbH')
        ->assertJsonPath('data.docks.2.bookings.0.license_plate', 'HHAB1234')
        ->assertJsonPath('data.docks.2.bookings.0.pallets', 24)
        ->assertJsonPath('data.docks.2.bookings.0.status', 'booked');
});

test('shows the bookings of every supplier, not only one', function () {
    scheduleDay();
    bookingOnDock(1, '2027-06-02 07:00', supplier: Supplier::factory()->approved()->create(['company_name' => 'Nord']));
    bookingOnDock(2, '2027-06-02 08:00', supplier: Supplier::factory()->approved()->create(['company_name' => 'Süd']));
    asWarehouse();

    $response = getJson('/api/v1/schedule?date=2027-06-02')->assertOk();

    expect($response->json('data.docks.0.bookings.0.company'))->toBe('Nord')
        ->and($response->json('data.docks.1.bookings.0.company'))->toBe('Süd');
});

test('sorts the bookings of a dock by time', function () {
    scheduleDay();
    bookingOnDock(1, '2027-06-02 11:00');
    bookingOnDock(1, '2027-06-02 07:00');
    bookingOnDock(1, '2027-06-02 09:00');
    asWarehouse();

    $times = array_column(
        getJson('/api/v1/schedule?date=2027-06-02')->json('data.docks.0.bookings'),
        'starts_at'
    );

    expect($times)->toBe([
        '2027-06-02T05:00:00Z',
        '2027-06-02T07:00:00Z',
        '2027-06-02T09:00:00Z',
    ]);
});

test('leaves a cancelled booking off the timeline', function () {
    scheduleDay();
    bookingOnDock(1, '2027-06-02 09:00', BookingStatus::Cancelled);
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonCount(0, 'data.docks.0.bookings');
});

test('keeps the other states on the timeline', function (BookingStatus $status) {
    scheduleDay();
    bookingOnDock(1, '2027-06-02 09:00', $status);
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonCount(1, 'data.docks.0.bookings')
        ->assertJsonPath('data.docks.0.bookings.0.status', $status->value);
})->with([
    BookingStatus::Booked,
    BookingStatus::Arrived,
    BookingStatus::InProgress,
    BookingStatus::Completed,
    BookingStatus::NoShow,
]);

test('shows an unreleased day as empty, with the hours of its weekday', function () {
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-09')
        ->assertOk()
        ->assertJsonPath('data.is_released', false)
        ->assertJsonPath('data.opens_at', '2027-06-09T04:00:00Z')
        ->assertJsonPath('data.closes_at', '2027-06-09T16:00:00Z')
        ->assertJsonCount(10, 'data.docks')
        ->assertJsonCount(0, 'data.docks.0.bookings')
        ->assertJsonPath('data.docks.0.usage.early', null);
});

test('shows a day that was saved but never released', function () {
    scheduleDay('2027-06-03', released: false);
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-03')
        ->assertOk()
        ->assertJsonPath('data.is_released', false)
        ->assertJsonPath('data.docks.0.usage.early', 'inbound');
});

test('says that a weekend is closed', function () {
    asWarehouse();

    expect(OpeningDefault::query()->where('weekday', 6)->firstOrFail()->is_closed)->toBeTrue();

    getJson('/api/v1/schedule?date=2027-06-05')
        ->assertOk()
        ->assertJsonPath('data.is_closed', true);
});

test('keeps a dock that is switched off on the list', function () {
    scheduleDay();
    Dock::query()->where('number', 7)->update(['is_active' => false]);
    asWarehouse();

    getJson('/api/v1/schedule?date=2027-06-02')
        ->assertOk()
        ->assertJsonCount(10, 'data.docks')
        ->assertJsonPath('data.docks.6.number', 7)
        ->assertJsonPath('data.docks.6.is_active', false)
        ->assertJsonPath('data.docks.0.is_active', true);
});

test('insists on a plain date', function (string $query) {
    asWarehouse();

    getJson("/api/v1/schedule?{$query}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
})->with([
    'no date at all' => [''],
    'a word' => ['date=soon'],
    'the german way round' => ['date=02.06.2027'],
    'with a time' => ['date=2027-06-02T06:00:00Z'],
]);

test('lets the warehouse and the admin in', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    getJson('/api/v1/schedule?date=2027-06-02')->assertOk();
})->with(['staff', 'admin']);

test('keeps a supplier out of the warehouse view', function () {
    actingAs(Supplier::factory()->approved()->create()->user);

    getJson('/api/v1/schedule?date=2027-06-02')->assertForbidden();
});

test('answers a guest with 401', function () {
    getJson('/api/v1/schedule?date=2027-06-02')->assertUnauthorized();
});
