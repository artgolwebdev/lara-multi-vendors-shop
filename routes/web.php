<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/run-seeder', function () {
    
    Artisan::call('db:seed', [
        '--force' => true,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Database seeded successfully',
        'output' => Artisan::output(),
    ]);
});

Route::get('/', function () {
    return view('store-app');
})->name('store.home');

Route::get('/shop', function () {
    return view('store-app');
})->name('store.shop');

Route::get('/categories', function () {
    return view('store-app');
})->name('store.categories');

Route::get('/categories/{category}', function () {
    return view('store-app');
})->name('store.category');

Route::get('/products/{product}', function () {
    return view('store-app');
})->name('store.product');

Route::get('/cart', function () {
    return view('store-app');
})->name('store.cart');

Route::get('/checkout', function () {
    return view('store-app');
})->name('store.checkout');

Route::get('/order-confirmation/{order}', function () {
    return view('store-app');
})->name('store.order-confirmation');

Route::get('/account', function () {
    return view('auth-app');
})->name('account');

Auth::routes();

// Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::view('/', 'admin-app')->name('dashboard');
    Route::view('/vendors', 'admin-app')->name('vendors.index');
    Route::view('/vendors/create', 'admin-app')->name('vendors.create');
    Route::view('/vendors/{vendor}/edit', 'admin-app')->name('vendors.edit');
    Route::view('/vendors/{vendor}', 'admin-app')->name('vendors.show');
    Route::view('/categories', 'admin-app')->name('categories.index');
    Route::view('/categories/create', 'admin-app')->name('categories.create');
    Route::view('/categories/{category}/edit', 'admin-app')->name('categories.edit');
    Route::view('/discount-rules', 'admin-app')->name('discount-rules.index');
    Route::view('/discount-rules/create', 'admin-app')->name('discount-rules.create');
    Route::view('/discount-rules/{discountRule}/edit', 'admin-app')->name('discount-rules.edit');
    Route::view('/products', 'admin-app')->name('products.index');
    Route::view('/products/create', 'admin-app')->name('products.create');
    Route::view('/products/{product}/edit', 'admin-app')->name('products.edit');
    Route::view('/customers', 'admin-app')->name('customers.index');
    Route::view('/customers/{customer}', 'admin-app')->name('customers.show');
    Route::view('/orders', 'admin-app')->name('orders.index');
    Route::view('/orders/{order}', 'admin-app')->name('orders.show');
});
