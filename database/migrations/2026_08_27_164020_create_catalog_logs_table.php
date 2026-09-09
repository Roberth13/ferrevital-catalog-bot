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
        Schema::create('catalog_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained('catalogs')->cascadeOnDelete();
            $table->string('codigo')->nullable();
            $table->string('status'); // created, updated, failed, duplicated
            $table->text('message')->nullable();
            $table->json('raw_data')->nullable(); // Guardar la metadata o línea extraída en caso de fallo
            $table->timestamps();

            $table->index('catalog_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_logs');
    }
};
