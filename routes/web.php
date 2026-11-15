<?php

use Illuminate\Support\Facades\Route;

Route::view('/{path?}', 'app')
    ->where('path', '^(?!(api|admin|up|storage|build)(/|$)).*$')
    ->name('spa');
