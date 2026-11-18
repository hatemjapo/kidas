<?php

use App\Http\Controllers\Api\ArrivalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SlotController;
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

    Route::middleware('role:staff,admin')->group(function (): void {
        Route::get('/schedule', [ScheduleController::class, 'show'])->name('api.schedule.show');

        Route::post('/bookings/{booking}/arrival', [ArrivalController::class, 'store'])
            ->whereNumber('booking')
            ->name('api.bookings.arrival');

        Route::patch('/bookings/{booking}/arrival', [ArrivalController::class, 'update'])
            ->whereNumber('booking')
            ->name('api.bookings.arrival.update');

        Route::post('/bookings/{booking}/plate-verification', [ArrivalController::class, 'verify'])
            ->whereNumber('booking')
            ->name('api.bookings.plate-verification');
    });

    Route::middleware('role:supplier')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

        Route::get('/slots', [SlotController::class, 'index'])->name('api.slots.index');

        Route::get('/bookings', [BookingController::class, 'index'])->name('api.bookings.index');
        Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])
            ->whereNumber('booking')
            ->name('api.bookings.show');

        Route::patch('/bookings/{booking}', [BookingController::class, 'update'])
            ->whereNumber('booking')
            ->name('api.bookings.update');

        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
            ->whereNumber('booking')
            ->name('api.bookings.cancel');

        Route::get('/vehicles', [VehicleController::class, 'index'])->name('api.vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('api.vehicles.store');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
            ->whereNumber('vehicle')
            ->name('api.vehicles.destroy');
    });
});
