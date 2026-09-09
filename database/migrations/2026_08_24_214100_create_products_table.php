<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_id')
                ->constrained('catalogs')
                ->cascadeOnDelete();

            $table->string('codigo')->nullable();
            $table->text('nombre')->nullable();

            $table->decimal('precio_bs', 15, 2)->nullable();
            $table->decimal('precio_divisa', 15, 2)->nullable();

            $table->longText('descripcion')->nullable();
            $table->text('garantia')->nullable();
            $table->text('condiciones')->nullable();
            $table->text('tiempo_entrega')->nullable();

            $table->longText('raw_text')->nullable();

            $table->decimal('confidence', 5, 2)->nullable();

            $table->timestamps();

            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};