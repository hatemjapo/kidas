<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

use function Pest\Laravel\get;

/**
 * @return array<string, list<string>>
 */
function documentedOperations(): array
{
    /** @var array{paths: array<string, array<string, mixed>>} $spec */
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $operations = [];

    foreach ($spec['paths'] as $path => $methods) {
        $operations[$path] = array_map('strtoupper', array_keys($methods));

        sort($operations[$path]);
    }

    ksort($operations);

    return $operations;
}

/**
 * @return array<string, list<string>>
 */
function registeredOperations(): array
{
    $operations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        $path = substr($route->uri(), strlen('api/v1'));

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $operations[$path][] = $method;
        }
    }

    foreach ($operations as $path => $methods) {
        sort($methods);

        $operations[$path] = $methods;
    }

    ksort($operations);

    return $operations;
}

test('describes every endpoint the application serves', function () {
    expect(array_keys(documentedOperations()))->toBe(array_keys(registeredOperations()));
});

test('describes every method of those endpoints', function () {
    expect(documentedOperations())->toBe(registeredOperations());
});

test('serves the reference', function () {
    get('/docs')
        ->assertOk()
        ->assertSee('swagger', false)
        ->assertSee(route('docs.spec'), false);
});

test('serves the specification itself', function () {
    get('/docs/openapi.yaml')
        ->assertOk()
        ->assertHeader('content-type', 'application/yaml');
});

test('keeps the reference out of the single page app', function () {
    expect(get('/docs')->getContent())->not->toContain('<div id="app">');
});

test('names every schema it points at', function () {
    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $referenced = [];
    array_walk_recursive($spec, function ($value, $key) use (&$referenced) {
        if ($key === '$ref') {
            $referenced[] = $value;
        }
    });

    foreach (array_unique($referenced) as $reference) {
        [$kind, $name] = array_slice(explode('/', $reference), 2);

        expect($spec['components'][$kind] ?? [])->toHaveKey($name);
    }
});

test('asks for a session everywhere but on the way in', function () {
    /** @var array{paths: array<string, array<string, array<string, mixed>>>} $spec */
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $open = [];

    foreach ($spec['paths'] as $path => $methods) {
        foreach ($methods as $operation) {
            if (($operation['security'] ?? null) === []) {
                $open[] = $path;
            }
        }
    }

    expect($open)->toBe(['/register', '/login']);
});
