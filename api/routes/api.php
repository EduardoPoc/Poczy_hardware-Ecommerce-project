<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductFilterController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('catalog', CatalogController::class)->name('catalog');
Route::get('products/filter', ProductFilterController::class)->name('products.filter');

Route::apiResource('products', ProductController::class)
    ->only(['index', 'show'])
    ->whereNumber('product');

Route::apiResource('brands', BrandController::class)
    ->only(['index', 'show'])
    ->whereNumber('brand');

Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show'])
    ->whereNumber('category');

Route::middleware('web')->group(function (): void {
    Route::post('login', [SessionController::class, 'store'])
        ->middleware('throttle:login')->name('login');
    Route::post('user', [UserController::class, 'store'])
        ->middleware('throttle:registration')->name('user.store');

    Route::middleware('auth:web')->group(function (): void {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

        Route::get('user', [UserController::class, 'show'])->name('user.show');
        Route::match(['put', 'patch'], 'user', [UserController::class, 'update'])->name('user.update');
        Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');
        Route::put('user/password', [PasswordController::class, 'update'])->name('user.password.update');

        Route::apiResource('addresses', AddressController::class)
            ->parameters(['addresses' => 'id'])
            ->whereNumber('id');

        Route::apiResource('orders', OrderController::class)
            ->only(['index', 'show', 'store', 'destroy'])
            ->parameters(['orders' => 'id'])
            ->whereNumber('id');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth:web', 'can:manage-shop'])->group(function (): void {
    Route::apiResource('products', AdminProductController::class)
        ->whereNumber('product');
    Route::apiResource('brands', AdminBrandController::class)
        ->whereNumber('brand');
    Route::apiResource('categories', AdminCategoryController::class)
        ->whereNumber('category');

    Route::apiResource('orders', AdminOrderController::class)
        ->only(['index', 'show', 'update'])
        ->parameters(['orders' => 'id'])
        ->whereNumber('id');

    Route::get('products/{id}/stock-movements', [StockMovementController::class, 'index'])
        ->whereNumber('id')->name('products.stock-movements.index');
    Route::post('products/{id}/stock-movements', [StockMovementController::class, 'store'])
        ->whereNumber('id')->name('products.stock-movements.store');
});
