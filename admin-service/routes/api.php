<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:jwt', 'admin', 'throttle:120,1'])->name('api.')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');
});
