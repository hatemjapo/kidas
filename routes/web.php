<?php

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::view('/docs', 'docs')->name('docs');

Route::get('/docs/openapi.yaml', fn () => Response::file(base_path('docs/openapi.yaml'), [
    'Content-Type' => 'application/yaml',
]))->name('docs.spec');

Route::view('/{path?}', 'app')
    ->where('path', '^(?!(api|admin|docs|up|storage|build)(/|$)).*$')
    ->name('spa');
