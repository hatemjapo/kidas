<?php

use App\Actions\Suppliers\DecideSupplierRegistration;
use App\Enums\SupplierStatus;
use App\Mail\SupplierApproved;
use App\Mail\SupplierRejected;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Mail::fake();
});

test('approving records the decision and tells the supplier', function () {
    $admin = User::factory()->admin()->create();
    $supplier = Supplier::factory()->pending()->create();

    app(DecideSupplierRegistration::class)->approve($supplier, $admin);

    expect($supplier->refresh()->status)->toBe(SupplierStatus::Approved)
        ->and($supplier->approved_by)->toBe($admin->id)
        ->and($supplier->approved_at)->not->toBeNull();

    Mail::assertSentCount(1);
    Mail::assertSent(SupplierApproved::class, fn (SupplierApproved $mail) => $mail->hasTo($supplier->user->email));
});

test('the approval mail carries the link to the login form', function () {
    $supplier = Supplier::factory()->pending()->create(['company_name' => 'Nordfracht GmbH']);

    $rendered = (new SupplierApproved($supplier))->render();

    expect($rendered)->toContain(url('/login'))
        ->and($rendered)->toContain('Nordfracht GmbH');
});

test('the mails escape a company name that carries markup', function () {
    $supplier = Supplier::factory()->pending()->create(['company_name' => "O'Keefe & <b>Sons</b>"]);

    $rendered = (new SupplierApproved($supplier))->render();

    expect($rendered)->toContain('O&#039;Keefe &amp; &lt;b&gt;Sons&lt;/b&gt;');
    expect($rendered)->not->toContain('<b>Sons</b>');
});

test('rejecting records the decision and tells the supplier', function () {
    $supplier = Supplier::factory()->pending()->create();

    app(DecideSupplierRegistration::class)->reject($supplier);

    expect($supplier->refresh()->status)->toBe(SupplierStatus::Rejected)
        ->and($supplier->approved_at)->toBeNull()
        ->and($supplier->approved_by)->toBeNull();

    Mail::assertSentCount(1);
    Mail::assertSent(SupplierRejected::class, fn (SupplierRejected $mail) => $mail->hasTo($supplier->user->email));
});

test('the rejection mail offers no way in', function () {
    $supplier = Supplier::factory()->pending()->create();

    expect((new SupplierRejected($supplier))->render())->not->toContain(url('/login'));
});

test('refuses to decide the same registration twice', function (string $state, string $decision) {
    $admin = User::factory()->admin()->create();
    $supplier = Supplier::factory()->{$state}()->create();

    $decide = app(DecideSupplierRegistration::class);

    expect(fn () => $decision === 'approve' ? $decide->approve($supplier, $admin) : $decide->reject($supplier))
        ->toThrow(DomainException::class);

    Mail::assertNothingSent();
})->with([
    'approving an approved one' => ['approved', 'approve'],
    'rejecting an approved one' => ['approved', 'reject'],
    'approving a rejected one' => ['rejected', 'approve'],
    'rejecting a rejected one' => ['rejected', 'reject'],
]);

test('an approved supplier gets through the login that turned them away before', function () {
    $admin = User::factory()->admin()->create();
    $supplier = Supplier::factory()->pending()->create();

    $credentials = ['email' => $supplier->user->email, 'password' => 'password'];

    postJson('/api/v1/login', $credentials)->assertForbidden();

    app(DecideSupplierRegistration::class)->approve($supplier, $admin);

    postJson('/api/v1/login', $credentials)
        ->assertOk()
        ->assertJsonPath('data.role', 'supplier');
});
