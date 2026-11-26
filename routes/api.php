<?php

use App\Http\Controllers\Api\Admin\DriverVerificationController;
use App\Http\Controllers\Api\Driver\AuthController as DriverAuthController;
use App\Http\Controllers\Api\Driver\AvailabilityController;
use App\Http\Controllers\Api\Driver\DeliveryController;
use App\Http\Controllers\Api\Driver\DeliveryOrderController;
use App\Http\Controllers\Api\Driver\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('driver')->group(function () {
    Route::post('/register', [DriverAuthController::class, 'register']);
    Route::post('/login', [DriverAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [DriverAuthController::class, 'me']);
        Route::post('/logout', [DriverAuthController::class, 'logout']);

        Route::middleware('driver.approved')->group(function () {
            Route::patch('/availability', [AvailabilityController::class, 'update']);
            Route::put('/location', [LocationController::class, 'update']);

            Route::get('/deliveries/current', [DeliveryController::class, 'current']);
            Route::get('/deliveries/history', [DeliveryController::class, 'history']);
            Route::post('/deliveries/{delivery}/start', [DeliveryController::class, 'start']);

            Route::post('/delivery-orders/{deliveryOrder}/accept', [DeliveryOrderController::class, 'accept']);
            Route::post('/delivery-orders/{deliveryOrder}/reject', [DeliveryOrderController::class, 'reject']);
            Route::post('/delivery-orders/{deliveryOrder}/complete', [DeliveryOrderController::class, 'complete']);
        });
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/drivers', [DriverVerificationController::class, 'index']);
    Route::get('/drivers/{driverProfile}', [DriverVerificationController::class, 'show']);
    Route::post('/drivers/{driverProfile}/approve', [DriverVerificationController::class, 'approve']);
    Route::post('/drivers/{driverProfile}/reject', [DriverVerificationController::class, 'reject']);
});
