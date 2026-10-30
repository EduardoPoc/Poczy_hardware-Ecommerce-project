<?php

use App\Http\Controllers\Api\AddressController;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[1-9][0-9]{0,17}');
Route::pattern('brand', '[1-9][0-9]{0,17}');

Route::get('/catalog', CatalogController::class)->name('api.catalog');
Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'name' => config('app.name'),
    ]);
})->name('api.health');

Route::get('/product', [ProductController::class, 'index'])->name('api.product.index');
Route::get('/product/{id}', [ProductController::class, 'show'])->name('api.product.show');
Route::get('/categoria', [CategoryController::class, 'index'])->name('api.category.index');
Route::get('/categoria/filter', ProductFilterController::class)->name('api.category.filter');
Route::get('/categoria/{id}', [CategoryController::class, 'show'])->name('api.category.show');

Route::middleware('web')->name('api.')->group(function (): void {
    Route::post('/user', [UserController::class, 'store'])->middleware('throttle:registration')->name('user.store');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
});

Route::middleware(['web', 'auth:web'])->name('api.')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::apiResource('address', AddressController::class)->parameters(['address' => 'id']);
    Route::get('/user', [UserController::class, 'show'])->name('user.show');
    Route::match(['put', 'patch'], '/user', [UserController::class, 'update'])->name('user.update');
    Route::delete('/user', [UserController::class, 'destroy'])->name('user.destroy');
    Route::put('/user/password', [PasswordController::class, 'update'])->name('user.password.update');
    Route::apiResource('order', OrderController::class)->except('update')->parameters(['order' => 'id']);

    Route::middleware('can:manage-shop')->group(function (): void {
        Route::apiResource('product', ProductController::class)->only(['store', 'update', 'destroy'])
            ->parameters(['product' => 'id']);
        Route::apiResource('categoria', CategoryController::class)->only(['store', 'update', 'destroy'])
            ->parameters(['categoria' => 'id'])->names([
                'store' => 'category.store', 'update' => 'category.update', 'destroy' => 'category.destroy',
            ]);
        Route::apiResource('brand', BrandController::class);
        Route::get('/product/{id}/stock-movements', [StockMovementController::class, 'index'])->name('stock.index');
        Route::post('/product/{id}/stock-movements', [StockMovementController::class, 'store'])->name('stock.store');
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::apiResource('product', AdminProductController::class)->only(['index', 'show'])->parameters(['product' => 'id']);
            Route::apiResource('categoria', AdminCategoryController::class)->only(['index', 'show'])->parameters(['categoria' => 'id']);
            Route::apiResource('order', AdminOrderController::class)->only(['index', 'show', 'update'])->parameters(['order' => 'id']);
        });
    });
});
