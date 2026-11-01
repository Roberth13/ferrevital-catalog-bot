<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();

            $table->string('filename');
            $table->string('original_filename');

            $table->string('status')
                ->default('uploaded');

            $table->unsignedInteger('total_products')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};