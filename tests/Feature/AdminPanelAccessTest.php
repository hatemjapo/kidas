<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('redirects guests from the admin panel to the login page', function () {
    get('/admin')->assertRedirect('/admin/login');
});

test('shows the login page to guests', function () {
    get('/admin/login')->assertOk();
});

test('lets an admin into the panel', function () {
    actingAs(User::factory()->admin()->create());

    get('/admin')->assertOk();
});

test('denies the panel to warehouse staff', function () {
    actingAs(User::factory()->staff()->create());

    get('/admin')->assertForbidden();
});

test('denies the panel to suppliers', function () {
    actingAs(User::factory()->supplier()->create());

    get('/admin')->assertForbidden();
});
