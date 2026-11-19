<?php

use App\Enums\BookingStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-07 09:00', 'Europe/Berlin'));

    seed(DemoSeeder::class);
});

test('creates the people the demo needs', function () {
    expect(User::query()->where('email', 'admin@kidas.test')->firstOrFail()->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', 'staff@kidas.test')->firstOrFail()->role)->toBe(UserRole::Staff)
        ->and(Supplier::query()->count())->toBe(3)
        ->and(Supplier::query()->where('status', SupplierStatus::Pending)->count())->toBe(1);
});

test('hands out the logins the README names', function (string $email) {
    postJson('/api/v1/login', ['email' => $email, 'password' => 'password'])->assertOk();
})->with(['admin@kidas.test', 'staff@kidas.test', 'nordfracht@kidas.test', 'aalbert@kidas.test']);

test('keeps the supplier who is still waiting out', function () {
    postJson('/api/v1/login', ['email' => 'neuhaus@kidas.test', 'password' => 'password'])->assertForbidden();
});

test('leaves nothing of the laravel skeleton behind', function () {
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('releases the coming days but not the weekend', function () {
    $released = CalendarDay::query()->where('is_released', true)->orderBy('date')->get();

    expect($released)->not->toBeEmpty()
        ->and($released->first()->date->toDateString())->toBe('2027-06-07')
        ->and($released->contains(fn (CalendarDay $day) => $day->date->isWeekend()))->toBeFalse()
        ->and($released->last()->date->lessThanOrEqualTo(CarbonImmutable::today()->addDays(13)))->toBeTrue();
});

test('gives every released day its docks', function () {
    $day = CalendarDay::query()->where('is_released', true)->orderBy('date')->firstOrFail();

    expect($day->dockUsages()->count())->toBe(20)
        ->and($day->dockUsages()->where('usage', 'outbound')->count())->toBe(6);
});

test('shows a booking in every state worth demonstrating', function () {
    $states = Booking::query()->pluck('status')->map(fn (BookingStatus $status) => $status->value)->unique()->sort()->values();

    expect($states->all())->toBe(['booked', 'cancelled', 'completed', 'in_progress', 'no_show']);
});

test('puts a lorry at the dock right now and the next one behind it', function () {
    $atTheDock = Booking::query()->where('status', BookingStatus::InProgress)->firstOrFail();

    expect($atTheDock->starts_at->isPast())->toBeTrue()
        ->and($atTheDock->ends_at->isFuture())->toBeTrue();

    $waiting = Booking::query()
        ->where('dock_id', $atTheDock->dock_id)
        ->where('status', BookingStatus::Booked)
        ->where('starts_at', '>=', $atTheDock->ends_at)
        ->orderBy('starts_at')
        ->first();

    expect($waiting)->not->toBeNull()
        ->and($waiting->starts_at->toIso8601ZuluString())->toBe($atTheDock->ends_at->toIso8601ZuluString());
});

test('writes a history for every booking', function () {
    Booking::query()->with('events')->get()->each(function (Booking $booking) {
        expect($booking->events)->not->toBeEmpty()
            ->and($booking->events->first()->to_status)->toBe(BookingStatus::Booked)
            ->and($booking->events->last()->to_status)->toBe($booking->status);
    });
});

test('puts every booking on a dock that serves its kind', function () {
    Booking::query()->with('dock')->get()->each(function (Booking $booking) {
        $day = CalendarDay::query()
            ->whereDate('date', $booking->starts_at->setTimezone('Europe/Berlin')->toDateString())
            ->firstOrFail();

        $usages = $booking->dock->dayUsages()
            ->where('calendar_day_id', $day->id)
            ->pluck('usage')
            ->map(fn ($usage) => $usage->value)
            ->unique();

        expect($day->is_released)->toBeTrue()
            ->and($usages)->toContain($booking->type->value);
    });
});

test('can be seeded again without doubling anything', function () {
    $before = Booking::query()->count();

    seed(DemoSeeder::class);

    expect(Booking::query()->count())->toBe($before)
        ->and(User::query()->count())->toBe(5)
        ->and(Supplier::query()->count())->toBe(3);
});

test('keeps the plates of the suppliers apart', function () {
    $nordfracht = Supplier::query()->where('company_name', 'Nordfracht GmbH')->firstOrFail();
    $aalbert = Supplier::query()->where('company_name', 'Aalbert Logistik')->firstOrFail();

    expect($nordfracht->vehicles()->pluck('license_plate')->sort()->values()->all())->toBe(['HHAB1234', 'HHCD5678'])
        ->and($aalbert->vehicles()->pluck('license_plate')->all())->toBe(['HBXY4321']);
});
