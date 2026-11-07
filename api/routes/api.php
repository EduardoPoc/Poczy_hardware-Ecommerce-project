<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductFilterController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('catalog', CatalogController::class)->name('catalog');
Route::get('products/filter', ProductFilterController::class)->name('products.filter');
Route::get('categories/{category}/filters', [CategoryController::class, 'filters'])
    ->whereNumber('category')->name('categories.filters');

Route::apiResource('products', ProductController::class)
    ->only(['index', 'show'])
    ->whereNumber('product');

Route::apiResource('brands', BrandController::class)
    ->only(['index', 'show'])
    ->whereNumber('brand');

Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show'])
    ->whereNumber('category');

Route::name('user.')->group(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('cart', [CartController::class, 'show'])->block()->name('cart.show');
        Route::post('cart/items', [CartController::class, 'storeItem'])->block()->name('cart.items.store');
        Route::patch('cart/items/{product}', [CartController::class, 'updateItem'])
            ->whereNumber('product')->block()->name('cart.items.update');
        Route::delete('cart/items/{product}', [CartController::class, 'destroyItem'])
            ->whereNumber('product')->block()->name('cart.items.destroy');

    });

    Route::post('login', [SessionController::class, 'store'])
        ->middleware(['web', 'throttle:login'])->block()->name('login');
    Route::post('user', [UserController::class, 'store'])
        ->middleware(['web', 'throttle:registration'])->block()->name('user.store');

    Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->group(function (): void {
        Route::post('checkout', CheckoutController::class)->block()->name('checkout');
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
        Route::post('orders/{id}/pay', [OrderController::class, 'pay'])
            ->whereNumber('id')->name('orders.pay');
    });
});

Route::prefix('admin')->name('admin.')->middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum', 'can:manage-shop'])->group(function (): void {
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

Route::post('auth/login', [SessionController::class, 'store'])
    ->defaults('token_auth', true)->middleware([EnsureFrontendRequestsAreStateful::class, 'throttle:login'])->name('auth.token');
Route::post('auth/register', [UserController::class, 'store'])
    ->middleware([EnsureFrontendRequestsAreStateful::class, 'throttle:registration'])->name('auth.register');
Route::post('auth/logout', [SessionController::class, 'destroy'])
    ->middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->name('auth.logout');
