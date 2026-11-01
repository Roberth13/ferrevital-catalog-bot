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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('precio_venta_usd', 12, 2)->nullable()->after('precio_divisa');
            $table->decimal('precio_venta_bs', 14, 2)->nullable()->after('precio_bs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['precio_venta_usd', 'precio_venta_bs']);
        });
    }
};

