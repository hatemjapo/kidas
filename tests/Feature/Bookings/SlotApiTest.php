<?php

use App\Enums\DockUsage;
use App\Enums\Shift;
use App\Models\CalendarDay;
use App\Models\Dock;
use App\Models\DockDayUsage;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-01 00:30', 'Europe/Berlin'));

    actingAs(Supplier::factory()->approved()->create()->user);
});

function openDay(string $date, DockUsage $usage = DockUsage::Inbound): CalendarDay
{
    $day = new CalendarDay;
    $day->date = Carbon\Carbon::parse($date);
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

test('answers with the bookable days and their start times', function () {
    openDay('2027-06-02');

    $response = getJson('/api/v1/slots?type=inbound&pallets=24')->assertOk();

    $response->assertJsonPath('data.0.date', '2027-06-02')
        ->assertJsonPath('data.0.slots.0.starts_at', '2027-06-02T04:00:00Z')
        ->assertJsonPath('data.0.slots.0.ends_at', '2027-06-02T04:45:00Z')
        ->assertJsonPath('data.0.slots.0.shift', 'early')
        ->assertJsonPath('meta.duration_minutes', 45);
});

test('never names a dock', function () {
    openDay('2027-06-02');

    $body = getJson('/api/v1/slots?type=inbound&pallets=24')->assertOk()->getContent();

    expect($body)->not->toContain('dock')
        ->and($body)->not->toContain('number');
});

test('hands out five days and pages on from there', function () {
    foreach (range(1, 9) as $offset) {
        openDay(CarbonImmutable::parse('2027-06-01')->addDays($offset)->toDateString());
    }

    $first = getJson('/api/v1/slots?type=inbound&pallets=24')->assertOk()->json('data');

    expect($first)->toHaveCount(5);

    $after = end($first)['date'];

    $second = getJson("/api/v1/slots?type=inbound&pallets=24&after={$after}")->assertOk()->json('data');

    expect(array_column($second, 'date'))->toBe(['2027-06-07', '2027-06-08', '2027-06-09', '2027-06-10'])
        ->and(array_intersect(array_column($first, 'date'), array_column($second, 'date')))->toBe([]);
});

test('pages back to the page in the middle, not to the first one', function () {
    foreach (range(1, 15) as $offset) {
        openDay(CarbonImmutable::parse('2027-06-01')->addDays($offset)->toDateString());
    }

    $first = getJson('/api/v1/slots?type=inbound&pallets=24')->assertOk()->json('data');
    $second = getJson('/api/v1/slots?type=inbound&pallets=24&after='.end($first)['date'])->assertOk()->json('data');
    $third = getJson('/api/v1/slots?type=inbound&pallets=24&after='.end($second)['date'])->assertOk()->json('data');

    $back = getJson('/api/v1/slots?type=inbound&pallets=24&before='.$third[0]['date'])->assertOk()->json('data');

    expect(array_column($back, 'date'))->toBe(array_column($second, 'date'))
        ->and(array_column($back, 'date'))->not->toBe(array_column($first, 'date'));
});

test('answers an empty list when nothing is bookable', function () {
    getJson('/api/v1/slots?type=inbound&pallets=24')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('answers an empty list beyond the last day', function () {
    openDay('2027-06-02');

    getJson('/api/v1/slots?type=inbound&pallets=24&after=2027-06-02')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('tells the duration that belongs to the load', function (int $pallets, int $minutes) {
    openDay('2027-06-02');

    getJson("/api/v1/slots?type=inbound&pallets={$pallets}")
        ->assertOk()
        ->assertJsonPath('meta.duration_minutes', $minutes);
})->with([
    [10, 15],
    [24, 45],
    [70, 105],
]);

test('separates the two kinds of traffic', function () {
    openDay('2027-06-02', DockUsage::Outbound);

    getJson('/api/v1/slots?type=inbound&pallets=24')->assertOk()->assertJsonCount(0, 'data');
    getJson('/api/v1/slots?type=outbound&pallets=24')->assertOk()->assertJsonCount(1, 'data');
});

test('insists on a sound question', function (string $query, string $field) {
    getJson("/api/v1/slots?{$query}")
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no type' => ['pallets=24', 'type'],
    'unknown type' => ['type=sideways&pallets=24', 'type'],
    'no pallets' => ['type=inbound', 'pallets'],
    'no load at all' => ['type=inbound&pallets=0', 'pallets'],
    'more than the lorry holds' => ['type=inbound&pallets=71', 'pallets'],
    'pallets as a word' => ['type=inbound&pallets=many', 'pallets'],
    'after is no date' => ['type=inbound&pallets=24&after=soon', 'after'],
    'both directions at once' => ['type=inbound&pallets=24&after=2027-06-02&before=2027-06-09', 'after'],
]);

test('keeps the warehouse out of the slots', function () {
    actingAs(User::factory()->staff()->create());

    getJson('/api/v1/slots?type=inbound&pallets=24')->assertForbidden();
});

test('lets the admin look at the slots, because the admin books too', function () {
    openDay('2027-06-02');

    actingAs(User::factory()->admin()->create());

    getJson('/api/v1/slots?type=inbound&pallets=24')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
