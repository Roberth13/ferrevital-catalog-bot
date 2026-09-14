<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalePriceController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Suppliers
Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');

// Catalogs
Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
Route::get('/catalogs/create', [CatalogController::class, 'create'])->name('catalogs.create');
Route::post('/catalogs', [CatalogController::class, 'store'])->name('catalogs.store');
Route::get('/catalogs/{catalog}', [CatalogController::class, 'show'])->name('catalogs.show');

// Products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulkAction');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggleActive');
Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');

// Sale Prices (Precio de Venta)
Route::get('/sale-prices', [SalePriceController::class, 'index'])->name('sale-prices.index');
Route::post('/sale-prices/apply', [SalePriceController::class, 'apply'])->name('sale-prices.apply');

