<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DiscountRuleController;
use App\Http\Controllers\Api\Admin\DiscountTestController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartQuoteController;
use App\Http\Controllers\Api\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController as PublicProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::get('/products', [PublicProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{product}', [PublicProductController::class, 'show'])->name('api.products.show');
Route::get('/categories', [PublicCategoryController::class, 'index'])->name('api.categories.index');
Route::get('/categories/{category}', [PublicCategoryController::class, 'show'])->name('api.categories.show');
Route::post('/cart/quote', CartQuoteController::class)->name('api.cart.quote');

Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/account/profile', [AccountController::class, 'profile']);
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::get('/account/discounts', [AccountController::class, 'discounts']);
    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/loyalty-rules', [CustomerController::class, 'loyaltyRules'])->name('customers.loyalty-rules');
    Route::post('/customers/{customer}/loyalty-rules/{discountRule}', [CustomerController::class, 'attachLoyaltyRule'])->name('customers.loyalty-rules.attach');
    Route::delete('/customers/{customer}/loyalty-rules/{discountRule}', [CustomerController::class, 'detachLoyaltyRule'])->name('customers.loyalty-rules.detach');
    Route::get('/customers/{customer}/orders', [AdminOrderController::class, 'customerOrders'])->name('customers.orders');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/sub-orders/{subOrder}/status', [AdminOrderController::class, 'updateSubOrderStatus'])->name('sub-orders.status');
    Route::post('/sub-orders/{subOrder}/notify', [AdminOrderController::class, 'notify'])->name('sub-orders.notify');
    Route::get('/vendors/{vendor}/orders', [AdminOrderController::class, 'vendorOrders'])->name('vendors.orders');

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
