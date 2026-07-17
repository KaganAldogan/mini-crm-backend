<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerInteractionController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/customers-stats', [CustomerController::class, 'stats']);
    Route::apiResource('customers', CustomerController::class);

    Route::get('/customers/{customer}/interactions', [CustomerInteractionController::class, 'index']);
    Route::post('/customers/{customer}/interactions', [CustomerInteractionController::class, 'store']);
    Route::put('/customers/{customer}/interactions/{interaction}', [CustomerInteractionController::class, 'update']);
    Route::delete('/customers/{customer}/interactions/{interaction}', [CustomerInteractionController::class, 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::apiResource('roles', RoleController::class);
    });
});
