<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('products.index');
});

Route::middleware('auth')->group(function () {
    // 1. Productos (todos los usuarios autenticados: admin y empleado)
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/export', [ProductController::class, 'exportXlsx'])->name('products.export');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    // 2. Rutas protegidas solo para Admin
    Route::middleware('admin')->group(function () {
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('/catalogs', [CatalogController::class, 'store'])->name('catalogs.store');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

        Route::get('/pricing', [PricingRuleController::class, 'index'])->name('pricing.index');
        Route::post('/pricing/apply', [PricingRuleController::class, 'apply'])->name('pricing.apply');
    });

    // Perfil estándar Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
