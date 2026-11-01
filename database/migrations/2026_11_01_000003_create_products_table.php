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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->string('codigo')->index();
            $table->string('variante')->default('');
            $table->string('nombre')->index();
            $table->decimal('precio_divisa', 12, 2)->nullable();
            $table->decimal('precio_bs', 14, 2)->nullable();
            $table->text('otros_precios')->nullable();
            $table->text('condiciones_proveedor')->nullable();
            $table->text('descripcion_tecnica')->nullable();
            $table->text('incluye')->nullable();
            $table->string('garantia')->nullable();
            $table->integer('pagina')->nullable();
            $table->boolean('revisar')->default(false);
            $table->string('revisar_motivo')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'codigo', 'variante']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

