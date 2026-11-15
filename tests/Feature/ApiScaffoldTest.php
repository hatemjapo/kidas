<?php

use Symfony\Component\HttpFoundation\Cookie;

use function Pest\Laravel\get;

test('issues a csrf cookie for the api client', function () {
    $response = get('/sanctum/csrf-cookie');

    $response->assertNoContent();

    $names = array_map(
        fn (Cookie $cookie) => $cookie->getName(),
        $response->headers->getCookies()
    );

    expect($names)->toContain('XSRF-TOKEN');
});
