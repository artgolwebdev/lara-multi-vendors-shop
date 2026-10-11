<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth-app');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

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
});
