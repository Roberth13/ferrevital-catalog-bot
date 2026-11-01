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
            $table->decimal('precio_venta_bs', 15, 2)->nullable()->after('precio_divisa');
            $table->decimal('precio_venta_divisa', 15, 2)->nullable()->after('precio_venta_bs');
            $table->string('sale_price_formula')->nullable()->after('precio_venta_divisa');
            $table->string('sale_price_base', 20)->nullable()->after('sale_price_formula');
            $table->timestamp('sale_price_applied_at')->nullable()->after('sale_price_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'precio_venta_bs',
                'precio_venta_divisa',
                'sale_price_formula',
                'sale_price_base',
                'sale_price_applied_at',
            ]);
        });
    }
};
