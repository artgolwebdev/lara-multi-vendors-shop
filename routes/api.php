<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DiscountRuleController;
use App\Http\Controllers\Api\Admin\DiscountTestController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartQuoteController;
use App\Http\Controllers\Api\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\ProductController as PublicProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::get('/products', [PublicProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{product}', [PublicProductController::class, 'show'])->name('api.products.show');
Route::get('/categories', [PublicCategoryController::class, 'index'])->name('api.categories.index');
Route::get('/categories/{category}', [PublicCategoryController::class, 'show'])->name('api.categories.show');
Route::post('/cart/quote', CartQuoteController::class)->name('api.cart.quote');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::apiResource('categories', CategoryController::class);
    Route::get('/discount-rules/options', [DiscountRuleController::class, 'options'])->name('discount-rules.options');
    Route::post('/discount-rules/test', DiscountTestController::class)->name('discount-rules.test');
    Route::patch('/discount-rules/{discountRule}/status', [DiscountRuleController::class, 'status'])->name('discount-rules.status');
    Route::apiResource('discount-rules', DiscountRuleController::class)->parameters(['discount-rules' => 'discountRule']);
    Route::apiResource('vendors', VendorController::class);
    Route::apiResource('products', ProductController::class);
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
        ->scopeBindings()
        ->name('products.images.destroy');
});
