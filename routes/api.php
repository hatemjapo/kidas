<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1')
    ->name('api.register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    Route::middleware('role:supplier')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

        Route::get('/vehicles', [VehicleController::class, 'index'])->name('api.vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('api.vehicles.store');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
            ->whereNumber('vehicle')
            ->name('api.vehicles.destroy');
    });
});
