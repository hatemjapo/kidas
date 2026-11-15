<?php

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('serves the react application at the root path', function () {
    get('/')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('serves the react application for nested client side routes', function () {
    get('/bookings/42')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('does not serve the react application for unknown api paths', function () {
    getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json');
});

test('does not serve the react application for the admin panel path', function () {
    get('/admin')->assertDontSee('id="app"', false);
});

test('serves the react application for paths that merely start like a reserved prefix', function (string $path) {
    get($path)
        ->assertOk()
        ->assertSee('id="app"', false);
})->with([
    '/uploads',
    '/administration',
    '/api-docs',
    '/buildings',
    '/storage-overview',
]);

test('keeps the health check endpoint intact', function () {
    get('/up')
        ->assertOk()
        ->assertDontSee('id="app"', false);
});
