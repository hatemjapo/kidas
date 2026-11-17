<?php

use App\Actions\Days\ResolveDayDefaults;
use App\Actions\Days\SaveDay;
use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\OpeningDefault;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function nextMonday(): CarbonImmutable
{
    return CarbonImmutable::today()->next(CarbonInterface::MONDAY);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dayState(array $overrides = []): array
{
    $usages = [];

    foreach (Dock::query()->orderBy('number')->get() as $dock) {
        $usages[$dock->id] = ['early' => 'inbound', 'late' => 'inbound'];
    }

    return array_merge([
        'is_released' => false,
        'is_closed' => false,
        'opens_at' => '06:00:00',
        'closes_at' => '18:00:00',
        'early_start' => '06:00:00',
        'early_end' => '14:00:00',
        'late_start' => '14:00:00',
        'late_end' => '18:00:00',
        'usages' => $usages,
    ], $overrides);
}

test('a day that was never opened falls back to the defaults of its weekday', function () {
    $state = app(ResolveDayDefaults::class)(nextMonday());

    expect($state['is_released'])->toBeFalse()
        ->and($state['is_closed'])->toBeFalse()
        ->and($state['opens_at'])->toBe('06:00:00')
        ->and($state['closes_at'])->toBe('18:00:00')
        ->and($state['early_start'])->toBe('06:00:00')
        ->and($state['late_end'])->toBe('18:00:00');
});

test('every dock starts on inbound', function () {
    $state = app(ResolveDayDefaults::class)(nextMonday());

    expect($state['usages'])->toHaveCount(10);

    foreach ($state['usages'] as $perShift) {
        expect($perShift)->toBe(['early' => 'inbound', 'late' => 'inbound']);
    }
});

test('a closed weekday arrives closed', function () {
    $saturday = CarbonImmutable::today()->next(CarbonInterface::SATURDAY);

    expect(app(ResolveDayDefaults::class)($saturday)['is_closed'])->toBeTrue();
});

test('saving writes the day and every dock at once', function () {
    $monday = nextMonday();

    app(SaveDay::class)($monday, dayState());

    $day = CalendarDay::query()->where('date', $monday->toDateString())->firstOrFail();

    expect($day->dockUsages)->toHaveCount(20)
        ->and($day->is_released)->toBeFalse()
        ->and($day->released_at)->toBeNull();
});

test('saving twice updates instead of duplicating', function () {
    $monday = nextMonday();

    app(SaveDay::class)($monday, dayState());
    app(SaveDay::class)($monday, dayState(['opens_at' => '07:00:00']));

    expect(CalendarDay::query()->count())->toBe(1)
        ->and(DockDayUsage::query()->count())->toBe(20)
        ->and(CalendarDay::query()->firstOrFail()->opens_at)->toBe('07:00:00');
});

test('releasing a day records when it happened', function () {
    $monday = nextMonday();

    $day = app(SaveDay::class)($monday, dayState(['is_released' => true]));

    expect($day->is_released)->toBeTrue()
        ->and($day->released_at)->not->toBeNull();

    $day = app(SaveDay::class)($monday, dayState(['is_released' => false]));

    expect($day->is_released)->toBeFalse()
        ->and($day->released_at)->toBeNull();
});

test('a released day keeps its own times when the defaults change afterwards', function () {
    $monday = nextMonday();

    app(SaveDay::class)($monday, dayState(['is_released' => true]));

    OpeningDefault::query()->where('weekday', 1)->firstOrFail()->update([
        'opens_at' => '09:00:00',
        'closes_at' => '12:00:00',
    ]);

    $state = app(ResolveDayDefaults::class)($monday);

    expect($state['opens_at'])->toBe('06:00:00')
        ->and($state['closes_at'])->toBe('18:00:00');
});

test('a day in the past cannot be changed', function () {
    expect(fn () => app(SaveDay::class)(CarbonImmutable::yesterday(), dayState()))
        ->toThrow(DomainException::class);
});

test('reads back the usage that was chosen', function () {
    $monday = nextMonday();
    $firstDock = Dock::query()->orderBy('number')->firstOrFail();

    $state = dayState();
    $state['usages'][$firstDock->id]['late'] = 'outbound';

    app(SaveDay::class)($monday, $state);

    $resolved = app(ResolveDayDefaults::class)($monday);

    expect($resolved['usages'][$firstDock->id])->toBe(['early' => 'inbound', 'late' => 'outbound']);

    $row = DockDayUsage::query()
        ->where('dock_id', $firstDock->id)
        ->where('shift', Shift::Late)
        ->firstOrFail();

    expect($row->usage)->toBe(DockUsage::Outbound);
});

test('refuses a second row for the same date', function () {
    $monday = nextMonday();

    app(SaveDay::class)($monday, dayState());

    expect(fn () => DB::table('calendar_days')->insert([
        'date' => $monday->toDateString(),
        'is_released' => false,
        'is_closed' => false,
        'opens_at' => '06:00:00',
        'closes_at' => '18:00:00',
        'early_start' => '06:00:00',
        'early_end' => '14:00:00',
        'late_start' => '14:00:00',
        'late_end' => '18:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('refuses a day whose shifts overlap', function () {
    expect(fn () => app(SaveDay::class)(nextMonday(), dayState([
        'early_end' => '15:00:00',
        'late_start' => '14:00:00',
    ])))->toThrow(QueryException::class);
});

test('drops the usages when the day is deleted', function () {
    $monday = nextMonday();

    app(SaveDay::class)($monday, dayState());

    CalendarDay::query()->firstOrFail()->delete();

    expect(DockDayUsage::query()->count())->toBe(0);
});
