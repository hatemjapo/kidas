<?php

use App\Filament\Resources\Docks\DockResource;
use App\Filament\Resources\Docks\Pages\ManageDocks;
use App\Models\Dock;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('lists all ten docks in order', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageDocks::class)
        ->assertCanSeeTableRecords(Dock::query()->orderBy('number')->get(), inOrder: true);
});

test('switches a dock off and on again', function () {
    actingAs(User::factory()->admin()->create());

    $dock = Dock::query()->where('number', 3)->firstOrFail();

    Livewire::test(ManageDocks::class)
        ->call('updateTableColumnState', 'is_active', (string) $dock->getKey(), false);

    expect($dock->refresh()->is_active)->toBeFalse();

    Livewire::test(ManageDocks::class)
        ->call('updateTableColumnState', 'is_active', (string) $dock->getKey(), true);

    expect($dock->refresh()->is_active)->toBeTrue();
});

test('offers no way to create or delete a dock', function () {
    actingAs(User::factory()->admin()->create());

    expect(DockResource::canCreate())->toBeFalse();

    Livewire::test(ManageDocks::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

test('keeps everyone but admins away from the docks', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/docks')->assertForbidden();
})->with(['staff', 'supplier']);

test('only an admin may see and change a dock', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $dock = Dock::query()->where('number', 1)->firstOrFail();

    expect($user->can('viewAny', Dock::class))->toBe($allowed)
        ->and($user->can('update', $dock))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);
