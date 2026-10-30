<?php

use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class)
    ->only(['index', 'show'])
    ->whereNumber('product');

Route::apiResource('brands', BrandController::class)
    ->only(['index', 'show'])
    ->whereNumber('brand');

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth:web', 'can:manage-shop'])->group(function (): void {
    Route::apiResource('products', AdminProductController::class)
        ->whereNumber('product');
    Route::apiResource('brands', AdminBrandController::class)
        ->whereNumber('brand');
});
