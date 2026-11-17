<?php

use App\Enums\Shift;
use App\Filament\Pages\Days;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
});

test('opens on the current month', function () {
    Livewire::test(Days::class)
        ->assertSet('month', CarbonImmutable::today()->format('Y-m'))
        ->assertSet('selectedDate', null);
});

test('walks through the months in both directions', function () {
    $month = CarbonImmutable::today()->format('Y-m');

    Livewire::test(Days::class)
        ->call('nextMonth')
        ->assertSet('month', CarbonImmutable::parse($month.'-01')->addMonth()->format('Y-m'))
        ->call('previousMonth')
        ->call('previousMonth')
        ->assertSet('month', CarbonImmutable::parse($month.'-01')->subMonth()->format('Y-m'));
});

test('opens a day with the defaults of its weekday', function () {
    $monday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);

    Livewire::test(Days::class)
        ->call('selectDay', $monday->toDateString())
        ->assertSet('selectedDate', $monday->toDateString())
        ->assertFormSet([
            'is_released' => false,
            'opens_at' => '06:00',
            'closes_at' => '18:00',
            'early_start' => '06:00',
            'late_end' => '18:00',
        ]);
});

test('walks through the days in both directions', function () {
    $monday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);

    Livewire::test(Days::class)
        ->call('selectDay', $monday->toDateString())
        ->call('nextDay')
        ->assertSet('selectedDate', $monday->addDay()->toDateString())
        ->call('previousDay')
        ->call('previousDay')
        ->assertSet('selectedDate', $monday->subDay()->toDateString());
});

test('saves the whole day at once', function () {
    $monday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
    $firstDock = Dock::query()->orderBy('number')->firstOrFail();

    Livewire::test(Days::class)
        ->call('selectDay', $monday->toDateString())
        ->fillForm(function (array $state) use ($firstDock): array {
            $state['is_released'] = true;
            $state['usages'][$firstDock->id]['early'] = 'outbound';

            return $state;
        })
        ->call('save')
        ->assertHasNoFormErrors();

    $day = CalendarDay::query()->where('date', $monday->toDateString())->firstOrFail();

    expect($day->is_released)->toBeTrue()
        ->and($day->dockUsages)->toHaveCount(20);

    $usage = DockDayUsage::query()
        ->where('calendar_day_id', $day->id)
        ->where('dock_id', $firstDock->id)
        ->where('shift', Shift::Early)
        ->firstOrFail();

    expect($usage->usage->value)->toBe('outbound');
});

test('marks released and unreleased days in the month', function () {
    $monday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);

    $page = Livewire::test(Days::class);

    $instance = $page->instance();
    assert($instance instanceof Days);

    $dayOf = fn (): array => Arr::first(
        $instance->monthDays(),
        fn (array $day): bool => $day['date'] === $monday->toDateString(),
    );

    expect($dayOf()['isReleased'])->toBeFalse();

    $page->call('selectDay', $monday->toDateString())
        ->fillForm(function (array $state): array {
            $state['is_released'] = true;

            return $state;
        })
        ->call('save');

    expect($dayOf()['isReleased'])->toBeTrue();
});

test('refuses to save a day in the past', function () {
    $yesterday = CarbonImmutable::yesterday();

    Livewire::test(Days::class)
        ->call('selectDay', $yesterday->toDateString())
        ->assertSee('cannot be changed');

    expect(CalendarDay::query()->count())->toBe(0);
});

test('keeps everyone but admins away from the days', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/days')->assertForbidden();
})->with(['staff', 'supplier']);
