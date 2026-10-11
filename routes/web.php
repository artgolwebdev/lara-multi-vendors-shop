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
    Route::view('/products', 'admin-app')->name('products.index');
    Route::view('/products/create', 'admin-app')->name('products.create');
    Route::view('/products/{product}/edit', 'admin-app')->name('products.edit');
});
