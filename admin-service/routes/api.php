<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:jwt', 'admin', 'throttle:120,1'])->name('api.')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');
    Route::post('products/{product}/image', [ProductImageController::class, 'store'])->name('products.image.store');
    Route::delete('products/{product}/image', [ProductImageController::class, 'destroy'])->name('products.image.destroy');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
});
