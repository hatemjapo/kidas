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

use function Pest\Laravel\actingAs;

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

function openedDay(DockUsage $usage = DockUsage::Inbound, string $date = '2027-06-02'): CalendarDay
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
function adminPayload(Supplier $supplier, array $overrides = []): array
{
    return array_merge([
        'supplier_id' => $supplier->id,
        'type' => 'inbound',
        'pallets' => 10,
        'starts_at' => '2027-06-02T06:00:00+02:00',
        'order_number' => 'PO-4711',
        'driver_phone' => '+49 170 1234567',
        'license_plate' => 'hh-ab 1234',
    ], $overrides);
}

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
