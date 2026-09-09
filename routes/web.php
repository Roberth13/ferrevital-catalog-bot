<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;

use App\Http\Controllers\ProductController;

use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/catalogs/create', [CatalogController::class, 'create'])
    ->name('catalogs.create');

Route::post('/catalogs', [CatalogController::class, 'store'])
    ->name('catalogs.store');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulkAction');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggleActive');
Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
