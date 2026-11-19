<?php

use App\Mail\BookingCancelled;
use App\Mail\BookingChanged;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2027-06-02 05:00', 'Europe/Berlin'));

    Mail::fake();
});

test('tells the supplier that the slot was moved', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-02T14:00:00+02:00'])
        ->assertOk();

    Mail::assertSentCount(1);
    Mail::assertSent(BookingChanged::class, fn (BookingChanged $mail) => $mail->hasTo($supplier->user->email));
});

test('names the old and the new time and the reason', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", [
        'starts_at' => '2027-06-02T14:00:00+02:00',
        'reason' => 'Dock maintenance',
    ])->assertOk();

    $sent = Mail::sent(BookingChanged::class)->firstOrFail();
    $rendered = $sent->render();

    expect($rendered)->toContain('Previously: 02.06.2027 10:00')
        ->and($rendered)->toContain('Now: 02.06.2027 14:00')
        ->and($rendered)->toContain('dock 1')
        ->and($rendered)->toContain('10 pallets')
        ->and($rendered)->toContain('Reason: Dock maintenance')
        ->and($rendered)->toContain(url('/bookings'));
});

test('leaves the reason out when there is none', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    $rendered = (new BookingChanged($booking, CarbonImmutable::parse('2027-06-02 08:00'), null))->render();

    expect($rendered)->not->toContain('Reason:');
});

test('escapes what the admin typed', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    $rendered = (new BookingChanged($booking, CarbonImmutable::parse('2027-06-02 08:00'), 'O\'Keefe & <b>Sons</b>'))
        ->render();

    expect($rendered)->toContain('O&#039;Keefe &amp; &lt;b&gt;Sons&lt;/b&gt;')
        ->and($rendered)->not->toContain('<b>Sons</b>');
});

test('writes a mail even when only the pallets change', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['pallets' => 40])->assertOk();

    Mail::assertSent(BookingChanged::class, fn (BookingChanged $mail) => $mail->hasTo($supplier->user->email));
});

test('tells the supplier that the slot was cancelled', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier);

    asAdmin();

    postJson("/api/v1/admin/bookings/{$booking->id}/cancel", ['reason' => 'Dock out of service'])->assertOk();

    Mail::assertSentCount(1);
    Mail::assertSent(BookingCancelled::class, fn (BookingCancelled $mail) => $mail->hasTo($supplier->user->email));

    $rendered = Mail::sent(BookingCancelled::class)->firstOrFail()->render();

    expect($rendered)->toContain('on 02.06.2027 10:00')
        ->and($rendered)->toContain('PO-4711')
        ->and($rendered)->toContain('Reason: Dock out of service')
        ->and($rendered)->toContain(url('/book'));
});

test('says nothing to the supplier about their own change', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier, '2027-06-02 14:00');

    actingAs($supplier->user);

    patchJson("/api/v1/bookings/{$booking->id}", ['starts_at' => '2027-06-02T16:00:00+02:00'])->assertOk();

    Mail::assertNothingSent();
});

test('says nothing to the supplier about their own cancellation', function () {
    openedDay();
    $supplier = Supplier::factory()->approved()->create();
    $booking = bookingOfSupplier($supplier, '2027-06-02 14:00');

    actingAs($supplier->user);

    postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

    Mail::assertNothingSent();
});

test('sends nothing when a change is refused', function () {
    openedDay();
    $booking = bookingOfSupplier(Supplier::factory()->approved()->create());

    asAdmin();

    patchJson("/api/v1/admin/bookings/{$booking->id}", ['starts_at' => '2027-06-03T10:00:00+02:00'])
        ->assertStatus(422);

    Mail::assertNothingSent();
});
