<?php

use App\Enums\SupplierStatus;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Mail\SupplierApproved;
use App\Mail\SupplierRejected;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Mail::fake();
});

test('lists the open registrations for an admin', function () {
    actingAs(User::factory()->admin()->create());

    $pending = Supplier::factory()->pending()->create(['company_name' => 'Nordfracht GmbH']);
    $approved = Supplier::factory()->approved()->create(['company_name' => 'Südlogistik AG']);

    Livewire::test(ManageSuppliers::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$approved]);
});

test('approves a registration from the table', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    $supplier = Supplier::factory()->pending()->create();

    Livewire::test(ManageSuppliers::class)
        ->callTableAction('approve', $supplier)
        ->assertHasNoActionErrors();

    expect($supplier->refresh()->status)->toBe(SupplierStatus::Approved)
        ->and($supplier->approved_by)->toBe($admin->id);

    Mail::assertSent(SupplierApproved::class);
});

test('rejects a registration from the table', function () {
    actingAs(User::factory()->admin()->create());

    $supplier = Supplier::factory()->pending()->create();

    Livewire::test(ManageSuppliers::class)
        ->callTableAction('reject', $supplier)
        ->assertHasNoActionErrors();

    expect($supplier->refresh()->status)->toBe(SupplierStatus::Rejected);

    Mail::assertSent(SupplierRejected::class);
});

test('offers no decision on a registration that is already decided', function (string $state) {
    actingAs(User::factory()->admin()->create());

    $supplier = Supplier::factory()->{$state}()->create();

    Livewire::test(ManageSuppliers::class)
        ->filterTable('status', $state)
        ->assertTableActionHidden('approve', $supplier)
        ->assertTableActionHidden('reject', $supplier);
})->with(['approved', 'rejected']);

test('offers no way to create a registration in the panel', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSuppliers::class)->assertActionDoesNotExist('create');
});

test('keeps everyone but admins away from the registrations', function (string $state) {
    actingAs(User::factory()->{$state}()->create());

    get('/admin/suppliers')->assertForbidden();
})->with(['staff', 'supplier']);
