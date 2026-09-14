<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crear tabla de proveedores (suppliers)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Insertar un proveedor por defecto "Genérico" para migrar datos existentes
        $genericSupplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Genérico',
            'slug' => 'generico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Agregar supplier_id a catalogs
        Schema::table('catalogs', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });

        // Asignar el proveedor genérico a los catálogos existentes
        DB::table('catalogs')->whereNull('supplier_id')->update([
            'supplier_id' => $genericSupplierId,
        ]);

        // 3. Agregar supplier_id a products y actualizar la restricción de unicidad
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('catalog_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });

        // Asignar el supplier_id del catálogo correspondiente a cada producto existente
        DB::statement("
            UPDATE products
            SET supplier_id = (
                SELECT supplier_id FROM catalogs WHERE catalogs.id = products.catalog_id
            )
            WHERE supplier_id IS NULL
        ");

        // Si aún existen productos con supplier_id NULL, asignar el proveedor genérico
        DB::table('products')->whereNull('supplier_id')->update([
            'supplier_id' => $genericSupplierId,
        ]);

        // Reemplazar la unicidad global en 'codigo' por una compuesta (supplier_id, codigo)
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->unique(['supplier_id', 'codigo'], 'products_supplier_codigo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_supplier_codigo_unique');
            $table->unique('codigo');
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::table('catalogs', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
