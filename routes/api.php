<?php

use App\Http\Controllers\Api\App\AttendanceController;
use App\Http\Controllers\Api\App\AuthController;
use App\Http\Controllers\Api\App\CartController;
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
    // Public: only category names and claim rules, no staff or distributor data.
    Route::get('/expense-categories', [ExpenseController::class, 'categories'])->middleware('throttle:60,1');

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

        // Cart (one per shop). Item edits are frequent taps, so they use the normal limit; checkout is a write.
        Route::get('/carts', [CartController::class, 'index']);
        Route::get('/cart', [CartController::class, 'show']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/items', [CartController::class, 'add']);
        Route::patch('/cart/items/{product}', [CartController::class, 'update'])->whereNumber('product');
        Route::delete('/cart/items/{product}', [CartController::class, 'remove'])->whereNumber('product');

        Route::middleware('throttle:app-write')->group(function () {
            Route::post('/parties', [CatalogController::class, 'storeParty']);
            Route::post('/cart/checkout', [CartController::class, 'checkout']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
            Route::post('/collections', [CollectionController::class, 'store']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
        });
    });
});
