<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Eliminar productos sin código (no pueden ser únicos)
        \Illuminate\Support\Facades\DB::table('products')->whereNull('codigo')->delete();

        // 2. Eliminar duplicados dejando solo el más reciente
        $duplicates = \Illuminate\Support\Facades\DB::select("
            SELECT MIN(id) as id, codigo
            FROM products
            GROUP BY codigo
            HAVING COUNT(codigo) > 1
        ");

        foreach ($duplicates as $duplicate) {
            \Illuminate\Support\Facades\DB::table('products')
                ->where('codigo', $duplicate->codigo)
                ->where('id', '!=', $duplicate->id)
                ->delete();
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['codigo']);
            $table->unique('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->index('codigo');
        });
    }
};
