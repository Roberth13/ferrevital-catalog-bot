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
        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->integer('first_page')->nullable();
            $table->integer('last_page')->nullable();
            $table->string('status')->default('pendiente'); // pendiente | procesando | listo | error
            $table->integer('total_pages')->nullable();
            $table->integer('total_products')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};

