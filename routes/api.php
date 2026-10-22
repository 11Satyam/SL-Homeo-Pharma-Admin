<?php

use App\Http\Controllers\Api\App\AttendanceController;
use App\Http\Controllers\Api\App\AuthController;
use App\Http\Controllers\Api\App\CatalogController;
use App\Http\Controllers\Api\App\CollectionController;
use App\Http\Controllers\Api\App\DashboardController;
use App\Http\Controllers\Api\App\ExpenseController;
use App\Http\Controllers\Api\App\OrderController;
use Illuminate\Support\Facades\Route;

// ---------------- Field staff mobile app (/api/app) ----------------
Route::prefix('app')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:app-login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

    Route::get('/collections', [CollectionController::class, 'index']);

    Route::middleware(['auth:sanctum', 'field.staff', 'throttle:app'])->group(function () {
        Route::get('/session', [AuthController::class, 'session']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:app-write');

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/parties', [CatalogController::class, 'parties']);
        Route::get('/parties/{shop}/invoices', [CatalogController::class, 'partyInvoices'])->whereNumber('shop');
        Route::get('/products', [CatalogController::class, 'products']);
        Route::get('/products/{product}', [CatalogController::class, 'product'])->whereNumber('product');

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');

        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::get('/attendance/history', [AttendanceController::class, 'history']);

        Route::get('/expenses', [ExpenseController::class, 'index']);

        Route::middleware('throttle:app-write')->group(function () {
            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
            Route::post('/collections', [CollectionController::class, 'store']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
        });
    });
});
