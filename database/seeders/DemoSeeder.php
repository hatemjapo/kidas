<?php

namespace Database\Seeders;

use App\Actions\Bookings\LogBookingEvent;
use App\Actions\Days\ResolveDayDefaults;
use App\Actions\Days\SaveDay;
use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public const ORDER_PREFIX = 'DEMO-';

    public const PASSWORD = 'password';

    private const OUTBOUND_DOCKS = [8, 9, 10];

    public function __construct(
        private readonly ResolveDayDefaults $resolveDayDefaults,
        private readonly SaveDay $saveDay,
        private readonly LogBookingEvent $logBookingEvent,
    ) {}

    public function run(): void
    {
        $admin = $this->keepUser('admin@kidas.test', 'Demo Admin', UserRole::Admin);
        $staff = $this->keepUser('staff@kidas.test', 'Demo Warehouse', UserRole::Staff);

        $nordfracht = $this->keepSupplier('nordfracht@kidas.test', 'Nordfracht GmbH', SupplierStatus::Approved, ['HHAB1234', 'HHCD5678']);
        $aalbert = $this->keepSupplier('aalbert@kidas.test', 'Aalbert Logistik', SupplierStatus::Approved, ['HBXY4321']);
        $this->keepSupplier('neuhaus@kidas.test', 'Neuhaus Spedition', SupplierStatus::Pending, []);

        $openDays = $this->releaseTheComingDays();

        $this->removeTheBookingsOfAnEarlierRun();

        $this->bookTheComingDays($openDays, $admin, $nordfracht, $aalbert);
        $this->playOutToday($openDays[0] ?? null, $staff, $admin, $nordfracht, $aalbert);
    }

    private function keepUser(string $email, string $name, UserRole $role): User
    {
        $user = User::query()->where('email', $email)->first() ?? new User;

        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make(self::PASSWORD);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @param  list<string>  $plates
     */
    private function keepSupplier(string $email, string $company, SupplierStatus $status, array $plates): Supplier
    {
        $user = $this->keepUser($email, $company, UserRole::Supplier);

        $supplier = Supplier::query()->where('user_id', $user->id)->first() ?? new Supplier;

        $supplier->user_id = $user->id;
        $supplier->company_name = $company;
        $supplier->company_phone = '+49 40 123456';
        $supplier->driver_phone = '+49 170 1234567';
        $supplier->status = $status;
        $supplier->save();

        foreach ($plates as $plate) {
            if (! $supplier->vehicles()->where('license_plate', $plate)->exists()) {
                $supplier->vehicles()->create(['license_plate' => $plate]);
            }
        }

        return $supplier;
    }

    /**
     * @return list<CalendarDay>
     */
    private function releaseTheComingDays(): array
    {
        $days = [];

        foreach (range(0, 13) as $offset) {
            $date = CarbonImmutable::today()->addDays($offset);
            $state = ($this->resolveDayDefaults)($date);

            if ($state['is_closed'] === true) {
                continue;
            }

            $state['is_released'] = true;
            $state['usages'] = $this->getUsagesForDemo();

            $days[] = ($this->saveDay)($date, $state);
        }

        return $days;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getUsagesForDemo(): array
    {
        $usages = [];

        foreach (Dock::query()->orderBy('number')->get() as $dock) {
            $usage = in_array($dock->number, self::OUTBOUND_DOCKS, true)
                ? DockUsage::Outbound
                : DockUsage::Inbound;

            foreach (Shift::cases() as $shift) {
                $usages[$dock->id][$shift->value] = $usage->value;
            }
        }

        return $usages;
    }

    private function removeTheBookingsOfAnEarlierRun(): void
    {
        $ids = Booking::query()
            ->where('order_number', 'like', self::ORDER_PREFIX.'%')
            ->pluck('id');

        BookingEvent::query()->whereIn('booking_id', $ids)->delete();
        Booking::query()->whereIn('id', $ids)->delete();
    }

    /**
     * @param  list<CalendarDay>  $openDays
     */
    private function bookTheComingDays(array $openDays, User $admin, Supplier $nordfracht, Supplier $aalbert): void
    {
        foreach (array_slice($openDays, 1, 4) as $index => $day) {
            $morning = $this->getMomentOn($day, sprintf('%02d:00', 8 + $index));

            $this->keepBooking($day, $nordfracht, $admin, DockUsage::Inbound, $morning, 30, 24, 'HHAB1234', BookingStatus::Booked);
            $this->keepBooking($day, $aalbert, $admin, DockUsage::Inbound, $morning->addMinutes(15), 45, 36, 'HBXY4321', BookingStatus::Booked);
            $this->keepBooking($day, $nordfracht, $admin, DockUsage::Inbound, $morning->addMinutes(30), 30, 12, 'HHCD5678', BookingStatus::Booked);

            $this->keepBooking($day, $aalbert, $admin, DockUsage::Outbound, $morning->addHours(2), 45, 36, 'HBXY4321', BookingStatus::Booked);
        }
    }

    private function playOutToday(?CalendarDay $today, User $staff, User $admin, Supplier $nordfracht, Supplier $aalbert): void
    {
        if (! $today instanceof CalendarDay || ! $today->date->isToday()) {
            return;
        }

        $now = $this->getQuarterHourNow();
        $opens = $this->getMomentOn($today, $today->opens_at);
        $closes = $this->getMomentOn($today, $today->closes_at);

        $behindUs = [
            [$now->subHours(2), 30, BookingStatus::Completed, $nordfracht, 'HHAB1234'],
            [$now->subMinutes(90), 30, BookingStatus::NoShow, $aalbert, 'HBXY4321'],
        ];

        foreach ($behindUs as [$startsAt, $minutes, $status, $supplier, $plate]) {
            if ($startsAt->greaterThanOrEqualTo($opens)) {
                $this->keepBooking($today, $supplier, $admin, DockUsage::Inbound, $startsAt, $minutes, 24, $plate, $status, $staff);
            }
        }

        $atTheDock = $now->subMinutes(15);

        if ($atTheDock->greaterThanOrEqualTo($opens) && $atTheDock->addMinutes(45)->lessThanOrEqualTo($closes)) {
            $this->keepBooking($today, $nordfracht, $admin, DockUsage::Inbound, $atTheDock, 45, 36, 'HHCD5678', BookingStatus::InProgress, $staff);

            $waiting = $atTheDock->addMinutes(45);

            if ($waiting->addMinutes(30)->lessThanOrEqualTo($closes)) {
                $this->keepBooking($today, $aalbert, $admin, DockUsage::Inbound, $waiting, 30, 24, 'HBXY4321', BookingStatus::Booked, $staff);
            }
        }

        $later = $now->addHours(2);

        if ($later->addMinutes(30)->lessThanOrEqualTo($closes)) {
            $this->keepBooking($today, $aalbert, $admin, DockUsage::Outbound, $later, 30, 24, 'HBXY4321', BookingStatus::Cancelled, $staff);
        }
    }

    private function getMomentOn(CalendarDay $day, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $day->date->toDateString().' '.$time,
            (string) config('booking.timezone'),
        );
    }

    private function getQuarterHourNow(): CarbonImmutable
    {
        $now = CarbonImmutable::now((string) config('booking.timezone'));

        return $now->setTime($now->hour, intdiv($now->minute, 15) * 15);
    }

    private function keepBooking(
        CalendarDay $day,
        Supplier $supplier,
        User $bookedBy,
        DockUsage $type,
        CarbonImmutable $startsAt,
        int $minutes,
        int $pallets,
        string $plate,
        BookingStatus $status,
        ?User $handledBy = null,
    ): ?Booking {
        $dock = $this->getFreeDock($day, $type, $startsAt, $startsAt->addMinutes($minutes), $status);

        if (! $dock instanceof Dock) {
            return null;
        }

        $booking = new Booking;
        $booking->type = $type;
        $booking->dock_id = $dock->id;
        $booking->supplier_id = $supplier->id;
        $booking->order_number = self::ORDER_PREFIX.str_pad((string) (Booking::query()->count() + 1), 3, '0', STR_PAD_LEFT);
        $booking->pallets = $pallets;
        $booking->license_plate = $plate;
        $booking->driver_phone = '+49 170 1234567';
        $booking->starts_at = Carbon::instance($startsAt->utc()->toDateTime());
        $booking->ends_at = Carbon::instance($startsAt->addMinutes($minutes)->utc()->toDateTime());
        $booking->status = $status;
        $booking->created_by = $bookedBy->id;

        $this->markTheWayThrough($booking, $startsAt, $status);

        $booking->save();

        $this->writeTheHistory($booking, $bookedBy, $handledBy ?? $bookedBy, $status);

        return $booking;
    }

    private function markTheWayThrough(Booking $booking, CarbonImmutable $startsAt, BookingStatus $status): void
    {
        if (in_array($status, [BookingStatus::Arrived, BookingStatus::InProgress, BookingStatus::Completed], true)) {
            $booking->arrived_at = Carbon::instance($startsAt->subMinutes(5)->utc()->toDateTime());
            $booking->arrived_plate = $booking->license_plate;
        }

        if (in_array($status, [BookingStatus::InProgress, BookingStatus::Completed], true)) {
            $booking->started_at = Carbon::instance($startsAt->utc()->toDateTime());
        }

        if ($status === BookingStatus::Completed) {
            $booking->completed_at = Carbon::instance($startsAt->addMinutes(20)->utc()->toDateTime());
        }

        if ($status === BookingStatus::Cancelled) {
            $booking->cancelled_at = now();
            $booking->cancel_reason = 'The load was not ready in time';
        }
    }

    private function writeTheHistory(Booking $booking, User $bookedBy, User $handledBy, BookingStatus $status): void
    {
        $this->logBookingEvent->logEventForBooking($booking, $bookedBy, null, BookingStatus::Booked);

        $steps = match ($status) {
            BookingStatus::Arrived => [[BookingStatus::Booked, BookingStatus::Arrived]],
            BookingStatus::InProgress => [[BookingStatus::Booked, BookingStatus::Arrived], [BookingStatus::Arrived, BookingStatus::InProgress]],
            BookingStatus::Completed => [
                [BookingStatus::Booked, BookingStatus::Arrived],
                [BookingStatus::Arrived, BookingStatus::InProgress],
                [BookingStatus::InProgress, BookingStatus::Completed],
            ],
            BookingStatus::Cancelled => [[BookingStatus::Booked, BookingStatus::Cancelled]],
            BookingStatus::NoShow => [[BookingStatus::Booked, BookingStatus::NoShow]],
            default => [],
        };

        foreach ($steps as [$from, $to]) {
            $this->logBookingEvent->logEventForBooking($booking, $handledBy, $from, $to);
        }
    }

    private function getFreeDock(
        CalendarDay $day,
        DockUsage $type,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        BookingStatus $status,
    ): ?Dock {
        $shift = $startsAt->format('H:i:s') < $day->early_end ? Shift::Early : Shift::Late;

        if (! in_array($status->value, BookingStatus::blocking(), true)) {
            return Dock::getDockForUsage($day, $shift, $type);
        }

        return Dock::getFreeDocksForSlot($day, $shift, $type, $startsAt->utc(), $endsAt->utc())->first();
    }
}
