<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/profile', 'profile.edit')->name('profile.edit');

    Route::resource('products', ProductController::class)->except('show');
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');
    Route::post('products/{product}/image', [ProductImageController::class, 'store'])->name('products.image.store');
    Route::delete('products/{product}/image', [ProductImageController::class, 'destroy'])->name('products.image.destroy');
});

require __DIR__.'/auth.php';
