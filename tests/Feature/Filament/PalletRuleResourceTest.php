<?php

use App\Filament\Resources\PalletRules\Pages\ManagePalletRules;
use App\Filament\Resources\PalletRules\PalletRuleResource;
use App\Models\PalletRule;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('lists the tiers from the smallest load upwards', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManagePalletRules::class)
        ->assertCanSeeTableRecords(PalletRule::query()->orderBy('max_pallets')->get(), inOrder: true);
});

test('changes the blocked time of a tier', function () {
    actingAs(User::factory()->admin()->create());

    $tier = PalletRule::query()->where('max_pallets', 20)->firstOrFail();

    Livewire::test(ManagePalletRules::class)
        ->callAction(TestAction::make('edit')->table($tier), data: ['duration_minutes' => 60])
        ->assertHasNoActionErrors();

    expect($tier->refresh()->duration_minutes)->toBe(60);
});

test('offers a quarter of an hour at a time up to three hours', function () {
    expect(array_keys(PalletRuleResource::durationOptions()))
        ->toBe([15, 30, 45, 60, 75, 90, 105, 120, 135, 150, 165, 180]);
});

test('writes every option in minutes', function (int $minutes, string $label) {
    expect(PalletRuleResource::formatDuration($minutes))->toBe($label);
})->with([
    [15, '15 min'],
    [45, '45 min'],
    [60, '60 min'],
    [75, '75 min'],
    [120, '120 min'],
    [180, '180 min'],
]);

test('refuses a duration that is not on offer', function () {
    actingAs(User::factory()->admin()->create());

    $tier = PalletRule::query()->where('max_pallets', 20)->firstOrFail();

    Livewire::test(ManagePalletRules::class)
        ->callAction(TestAction::make('edit')->table($tier), data: ['duration_minutes' => 20])
        ->assertHasActionErrors(['duration_minutes']);

    expect($tier->refresh()->duration_minutes)->toBe(30);
});

test('offers no way to create or delete a tier', function () {
    actingAs(User::factory()->admin()->create());

    expect(PalletRuleResource::canCreate())->toBeFalse();

    Livewire::test(ManagePalletRules::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

test('keeps everyone but admins away from the pallet durations', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/pallet-rules')->assertForbidden();
})->with(['staff', 'supplier']);

test('only an admin may see and change a tier', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $tier = PalletRule::query()->where('max_pallets', 10)->firstOrFail();

    expect($user->can('viewAny', PalletRule::class))->toBe($allowed)
        ->and($user->can('update', $tier))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);
