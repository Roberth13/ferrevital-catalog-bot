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
            $table->unsignedInteger('page_number')->nullable()->after('catalog_id');
            $table->string('extraction_method', 30)->nullable()->default('text')->after('confidence');
            $table->string('ai_provider', 50)->nullable()->after('extraction_method');
            $table->string('ai_model', 50)->nullable()->after('ai_provider');
            $table->string('prompt_version', 20)->nullable()->after('ai_model');
            $table->string('parser_version', 20)->nullable()->default('v1')->after('prompt_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'page_number',
                'extraction_method',
                'ai_provider',
                'ai_model',
                'prompt_version',
                'parser_version',
            ]);
        });
    }
};
