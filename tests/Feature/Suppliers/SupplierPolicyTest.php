<?php

use App\Models\Supplier;
use App\Models\User;

test('only an admin may see the registrations', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();

    expect($user->can('viewAny', Supplier::class))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);

test('only an admin may decide a registration', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $supplier = Supplier::factory()->pending()->create();

    expect($user->can('view', $supplier))->toBe($allowed)
        ->and($user->can('decide', $supplier))->toBe($allowed);
})->with([
    ['admin', true],
    ['staff', false],
    ['supplier', false],
]);
