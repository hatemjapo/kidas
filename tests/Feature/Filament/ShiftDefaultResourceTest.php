<?php

use App\Enums\Shift;
use App\Filament\Resources\ShiftDefaults\Pages\ManageShiftDefaults;
use App\Filament\Resources\ShiftDefaults\ShiftDefaultResource;
use App\Models\ShiftDefault;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('lists both shifts in the order of the day', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageShiftDefaults::class)
        ->assertCanSeeTableRecords(ShiftDefault::query()->orderBy('starts_at')->get(), inOrder: true);
});

test('moves a shift', function () {
    actingAs(User::factory()->admin()->create());

    $early = ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail();

    Livewire::test(ManageShiftDefaults::class)
        ->callAction(TestAction::make('edit')->table($early), data: [
            'starts_at' => '07:00',
            'ends_at' => '13:00',
        ])
        ->assertHasNoActionErrors();

    expect($early->refresh()->starts_at)->toBe('07:00:00')
        ->and($early->ends_at)->toBe('13:00:00');
});

test('refuses a shift that would overlap the other one', function () {
    actingAs(User::factory()->admin()->create());

    $early = ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail();

    Livewire::test(ManageShiftDefaults::class)
        ->callAction(TestAction::make('edit')->table($early), data: [
            'starts_at' => '06:00',
            'ends_at' => '15:00',
        ])
        ->assertHasActionErrors(['ends_at']);

    expect($early->refresh()->ends_at)->toBe('14:00:00');
});

test('allows a shift that ends exactly when the other one starts', function () {
    actingAs(User::factory()->admin()->create());

    $late = ShiftDefault::query()->where('shift', Shift::Late)->firstOrFail();

    Livewire::test(ManageShiftDefaults::class)
        ->callAction(TestAction::make('edit')->table($late), data: [
            'starts_at' => '14:00',
            'ends_at' => '20:00',
        ])
        ->assertHasNoActionErrors();

    expect($late->refresh()->ends_at)->toBe('20:00:00');
});

test('offers no way to create or delete a shift', function () {
    actingAs(User::factory()->admin()->create());

    expect(ShiftDefaultResource::canCreate())->toBeFalse();

    Livewire::test(ManageShiftDefaults::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

test('keeps everyone but admins away from the shift times', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/shift-defaults')->assertForbidden();
})->with(['staff', 'supplier']);

test('only an admin may see and change the shift times', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $early = ShiftDefault::query()->where('shift', Shift::Early)->firstOrFail();

    expect($user->can('viewAny', ShiftDefault::class))->toBe($allowed)
        ->and($user->can('update', $early))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);
