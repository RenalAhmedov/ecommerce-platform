<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
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

    Route::resource('categories', CategoryController::class)->except('show');
    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('categories.restore');
});

require __DIR__.'/auth.php';
