<?php

use App\Http\Controllers\Api\Driver\AuthController as DriverAuthController;
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
        // Route::post('/profile', [DriverAuthController::class, 'completeProfile']);
        // Route::post('/complete-profile', [DriverAuthController::class, 'completeProfile']);
    });
});
