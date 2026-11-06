<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/about', [HomeController::class, 'about']);
Route::get('/contact', [HomeController::class, 'contact']);
Route::get('/service', [HomeController::class, 'service']);
Route::get('/project', [HomeController::class, 'project']);
Route::get('/product/{id}', [HomeController::class, 'productDetail']);
Route::get('/terms', [HomeController::class, 'terms']);
Route::get('/privacy', [HomeController::class, 'privacy']);
Route::get('/faq', [HomeController::class, 'faq']);
Route::get('/video', [HomeController::class, 'video']);

// ---------------- Distribution ERP ----------------
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DistributorStockController as DS;
use App\Http\Controllers\ErpController as E;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::redirect('/admin', '/login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('erp')->name('erp.')->middleware(['auth', 'role'])->group(function () {
    Route::get('/', [E::class, 'dashboard'])->name('dashboard');
    Route::get('/help', [E::class, 'help'])->name('help');
    Route::get('/products', [E::class, 'products'])->name('products');
    Route::get('/stock', [E::class, 'stock'])->name('stock');
    Route::get('/ledger', [E::class, 'ledger'])->name('ledger');
    Route::get('/transfers', [E::class, 'transfers'])->name('transfers');
    Route::post('/transfers/{transfer}/{action}', [E::class, 'transferAction'])->whereIn('action', ['approve', 'dispatch', 'receive'])->name('transfers.action');
    Route::get('/shops', [E::class, 'shops'])->name('shops');
    Route::get('/shops/{shop}', [E::class, 'shop'])->name('shop');
    Route::get('/orders', [E::class, 'orders'])->name('orders');
    Route::get('/orders/stock', [E::class, 'orderStock'])->name('orders.stock');
    Route::get('/invoices', [E::class, 'invoices'])->name('invoices');
    Route::get('/invoices/{invoice}', [E::class, 'invoice'])->name('invoice');
    Route::get('/payments', [E::class, 'payments'])->name('payments');

    Route::middleware('role:distributor,staff')->group(function () {
        Route::post('/orders', [E::class, 'saveOrder'])->name('orders.store');
        Route::post('/orders/{order}/{action}', [E::class, 'orderAction'])->name('orders.action');
        Route::post('/payments', [E::class, 'savePayment'])->name('payments.store');
    });
    Route::middleware('role:distributor,accounts_manager')->group(function () {
        Route::post('/payments/{payment}/verify', [E::class, 'verifyPayment'])->name('payments.verify');
        Route::post('/expenses/{expense}/review', [E::class, 'reviewExpense'])->name('expenses.review');
    });
    // Field staff records: managers and auditors, not other staff.
    Route::middleware('role:distributor,accounts_manager,viewer')->group(function () {
        Route::get('/attendance', [E::class, 'attendance'])->name('attendance');
        Route::get('/expenses', [E::class, 'expenses'])->name('expenses');
        Route::get('/expenses/{expense}/receipt', [E::class, 'expenseReceipt'])->name('expenses.receipt');
    });
    Route::middleware('role:distributor')->group(function () {
        Route::post('/shops', [E::class, 'saveShop'])->name('shops.store');
        Route::post('/shops/{shop}', [E::class, 'updateShop'])->name('shops.update');
        Route::get('/users', [E::class, 'users'])->name('users');
        Route::post('/users', [E::class, 'saveUser'])->name('users.store');
        Route::post('/users/{user}/status', [E::class, 'setUserStatus'])->name('users.status');
        Route::post('/users/{user}/password', [E::class, 'setUserPassword'])->name('users.password');
        Route::post('/users/{user}/phone', [E::class, 'setUserPhone'])->name('users.phone');
    });
    Route::middleware('role:inventory_manager')->group(function () {
        Route::post('/products', [E::class, 'saveProduct'])->name('products.store');
        Route::post('/products/{product}', [E::class, 'saveProduct'])->name('products.update');
        Route::post('/warehouses', [E::class, 'saveWarehouse'])->name('warehouses.store');
        Route::post('/stock/receive', [E::class, 'receiveStock'])->name('stock.receive');
        Route::post('/stock/adjust', [E::class, 'adjustStock'])->name('stock.adjust');
        Route::post('/transfers', [E::class, 'saveTransfer'])->name('transfers.store');
        Route::get('/transfers/stock', [E::class, 'transferStock'])->name('transfers.stock');
    });
    Route::middleware('role:viewer')->get('/audit', [E::class, 'audit'])->name('audit');
    // Admin-only: Role middleware always admits admin, and no real role is named "__admin_only".
    Route::middleware('role:__admin_only')->group(function () {
        Route::get('/distributors', [E::class, 'distributors'])->name('distributors');
        Route::get('/distributors/report', [DS::class, 'report'])->name('distributors.report');
        Route::get('/distributors/{distributor}', [DS::class, 'show'])->whereNumber('distributor')->name('distributors.show');
        Route::get('/distributors/{distributor}/export', [DS::class, 'export'])->whereNumber('distributor')->name('distributors.export');
        Route::post('/distributors/{distributor}/stock', [DS::class, 'operate'])->whereNumber('distributor')->name('distributors.stock');
        Route::get('/ledger/{movement}', [DS::class, 'movement'])->whereNumber('movement')->name('ledger.show');
        Route::post('/distributors', [E::class, 'saveDistributor'])->name('distributors.store');
        Route::post('/distributors/{distributor}', [E::class, 'updateDistributor'])->name('distributors.update');
        Route::post('/distributors/{distributor}/status', [E::class, 'setDistributorStatus'])->name('distributors.status');
        Route::post('/distributors/{distributor}/geofence', [E::class, 'setGeofence'])->name('distributors.geofence');
        Route::post('/ledger/{movement}/reverse', [E::class, 'reverseMovement'])->name('ledger.reverse');
        Route::post('/warehouses/{location}/delete', [E::class, 'deleteWarehouse'])->name('warehouses.delete');
    });
});
