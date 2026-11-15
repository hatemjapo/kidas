<?php

use function Pest\Laravel\get;

test('returns a successful response for the home page', function () {
    get('/')->assertStatus(200);
});
