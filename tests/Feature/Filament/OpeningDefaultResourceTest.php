<?php

use App\Enums\Weekday;
use App\Filament\Resources\OpeningDefaults\OpeningDefaultResource;
use App\Filament\Resources\OpeningDefaults\Pages\ManageOpeningDefaults;
use App\Models\OpeningDefault;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('lists the seven weekdays in order', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageOpeningDefaults::class)
        ->assertCanSeeTableRecords(OpeningDefault::query()->orderBy('weekday')->get(), inOrder: true);
});

test('changes the opening hours of a day', function () {
    actingAs(User::factory()->admin()->create());

    $monday = OpeningDefault::query()->where('weekday', Weekday::Monday)->firstOrFail();

    Livewire::test(ManageOpeningDefaults::class)
        ->callAction(TestAction::make('edit')->table($monday), data: [
            'is_closed' => false,
            'opens_at' => '07:30',
            'closes_at' => '16:00',
        ])
        ->assertHasNoActionErrors();

    expect($monday->refresh()->opens_at)->toBe('07:30:00')
        ->and($monday->closes_at)->toBe('16:00:00')
        ->and($monday->is_closed)->toBeFalse();
});

test('closes a day for the whole week ahead', function () {
    actingAs(User::factory()->admin()->create());

    $friday = OpeningDefault::query()->where('weekday', Weekday::Friday)->firstOrFail();

    Livewire::test(ManageOpeningDefaults::class)
        ->callAction(TestAction::make('edit')->table($friday), data: ['is_closed' => true])
        ->assertHasNoActionErrors();

    expect($friday->refresh()->is_closed)->toBeTrue();
});

test('refuses a window that closes before it opens', function () {
    actingAs(User::factory()->admin()->create());

    $monday = OpeningDefault::query()->where('weekday', Weekday::Monday)->firstOrFail();

    Livewire::test(ManageOpeningDefaults::class)
        ->callAction(TestAction::make('edit')->table($monday), data: [
            'is_closed' => false,
            'opens_at' => '18:00',
            'closes_at' => '06:00',
        ])
        ->assertHasActionErrors(['closes_at']);

    expect($monday->refresh()->opens_at)->toBe('06:00:00');
});

test('offers no way to create or delete opening hours', function () {
    actingAs(User::factory()->admin()->create());

    expect(OpeningDefaultResource::canCreate())->toBeFalse();

    Livewire::test(ManageOpeningDefaults::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

test('keeps everyone but admins away from the opening hours', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/opening-defaults')->assertForbidden();
})->with(['staff', 'supplier']);

test('only an admin may see and change the opening hours', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $monday = OpeningDefault::query()->where('weekday', Weekday::Monday)->firstOrFail();

    expect($user->can('viewAny', OpeningDefault::class))->toBe($allowed)
        ->and($user->can('update', $monday))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);
