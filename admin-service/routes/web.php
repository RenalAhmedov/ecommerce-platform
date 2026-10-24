<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShopSearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/shop-search', ShopSearchController::class)->name('shop-search');
    Route::view('/profile', 'profile.edit')->name('profile.edit');

    // Fixed paths are registered before products/{product} so "export" is never read as an id.
    Route::get('products/export', ProductExportController::class)->name('products.export');
    Route::get('products/import', [ProductImportController::class, 'index'])->name('products.import.index');
    Route::post('products/import', [ProductImportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('products.import.store');

    Route::resource('products', ProductController::class)->except('show');
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');
    Route::post('products/{product}/image', [ProductImageController::class, 'store'])->name('products.image.store');
    Route::delete('products/{product}/image', [ProductImageController::class, 'destroy'])->name('products.image.destroy');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    Route::resource('categories', CategoryController::class)->except('show');
    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('categories.restore');

    Route::get('reports/sales', [ReportController::class, 'index'])->name('reports.sales');
    Route::get('reports/sales.csv', [ReportController::class, 'csv'])->name('reports.sales.csv');
    Route::get('reports/sales.pdf', [ReportController::class, 'pdf'])->name('reports.sales.pdf');

    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/restore', [UserController::class, 'restore'])
        ->withTrashed()
        ->name('users.restore');
});

require __DIR__.'/auth.php';
