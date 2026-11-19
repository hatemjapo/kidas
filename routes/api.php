<?php

use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\ArrivalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\HandlingController;
use App\Http\Controllers\Api\NoShowController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1')
    ->name('api.register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('throttle:writes')
        ->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    Route::middleware('role:staff,admin')->group(function (): void {
        Route::get('/schedule', [ScheduleController::class, 'show'])->name('api.schedule.show');

        Route::post('/bookings/{bookingId}/arrival', [ArrivalController::class, 'store'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.arrival');

        Route::patch('/bookings/{bookingId}/arrival', [ArrivalController::class, 'update'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.arrival.update');

        Route::post('/bookings/{bookingId}/plate-verification', [ArrivalController::class, 'verify'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.plate-verification');

        Route::post('/bookings/{bookingId}/handling', [HandlingController::class, 'store'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.handling');

        Route::post('/bookings/{bookingId}/completion', [HandlingController::class, 'complete'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.completion');

        Route::post('/bookings/{bookingId}/no-show', [NoShowController::class, 'store'])
            ->middleware('throttle:warehouse')
            ->whereNumber('bookingId')
            ->name('api.bookings.no-show');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.suppliers.index');

        Route::get('/suppliers/{supplier}/vehicles', [SupplierController::class, 'vehicles'])
            ->whereNumber('supplier')
            ->name('api.suppliers.vehicles');

        Route::post('/admin/bookings', [AdminBookingController::class, 'store'])
            ->middleware('throttle:writes')->name('api.admin.bookings.store');

        Route::patch('/admin/bookings/{bookingId}', [AdminBookingController::class, 'update'])
            ->middleware('throttle:writes')
            ->whereNumber('bookingId')
            ->name('api.admin.bookings.update');

        Route::post('/admin/bookings/{bookingId}/cancel', [AdminBookingController::class, 'cancel'])
            ->middleware('throttle:writes')
            ->whereNumber('bookingId')
            ->name('api.admin.bookings.cancel');
    });

    Route::middleware('role:supplier,admin')->group(function (): void {
        Route::get('/slots', [SlotController::class, 'index'])->name('api.slots.index');
    });

    Route::middleware('role:supplier')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:writes')->name('api.profile.update');

        Route::get('/bookings', [BookingController::class, 'index'])->name('api.bookings.index');
        Route::post('/bookings', [BookingController::class, 'store'])
            ->middleware('throttle:writes')->name('api.bookings.store');
        Route::get('/bookings/{bookingId}', [BookingController::class, 'show'])
            ->whereNumber('bookingId')
            ->name('api.bookings.show');

        Route::get('/bookings/{bookingId}/status', [BookingController::class, 'status'])
            ->whereNumber('bookingId')
            ->name('api.bookings.status');

        Route::patch('/bookings/{bookingId}', [BookingController::class, 'update'])
            ->middleware('throttle:writes')
            ->whereNumber('bookingId')
            ->name('api.bookings.update');

        Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel'])
            ->middleware('throttle:writes')
            ->whereNumber('bookingId')
            ->name('api.bookings.cancel');

        Route::get('/vehicles', [VehicleController::class, 'index'])->name('api.vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware('throttle:writes')->name('api.vehicles.store');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
            ->middleware('throttle:writes')
            ->whereNumber('vehicle')
            ->name('api.vehicles.destroy');
    });
});
