<?php

namespace Tests\Unit;

use App\Contracts\AiProviderInterface;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtractionMetadataTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create(['name' => 'Ferretería Test', 'slug' => 'ferreteria-test']);
        $this->catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'test_catalog.pdf',
            'original_filename' => 'test_catalog.pdf',
            'status' => 'completed',
        ]);
    }

    public function test_deterministic_text_extraction_records_correct_metadata()
    {
        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'TXT-001',
            'nombre' => 'Producto Texto Determinístico',
            'precio_divisa' => 12.00,
            'extraction_method' => 'text',
            'parser_version' => 'v1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'extraction_method' => 'text',
            'ai_provider' => null,
            'ai_model' => null,
            'prompt_version' => null,
            'parser_version' => 'v1',
        ]);
    }

    public function test_ocr_extraction_records_page_number_and_ocr_method()
    {
        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'page_number' => 5,
            'codigo' => 'OCR-005',
            'nombre' => 'Producto OCR Página 5',
            'precio_divisa' => 45.00,
            'extraction_method' => 'ocr',
            'parser_version' => 'v1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'page_number' => 5,
            'extraction_method' => 'ocr',
            'ai_provider' => null,
            'ai_model' => null,
            'prompt_version' => null,
            'parser_version' => 'v1',
        ]);
    }

    public function test_ai_extraction_job_records_ai_provider_model_and_prompt_version()
    {
        Http::fake();

        $fakeAiProvider = new class implements AiProviderInterface {
            public function extractProductsFromChunk(string $chunkText): array {
                return [
                    [
                        'codigo' => 'AI-777',
                        'nombre' => 'Producto Extraído por AI',
                        'precio_divisa' => 77.00,
                        'precio_bs' => 0,
                        'descripcion' => 'Specs AI',
                        'garantia' => '2 años',
                        'condiciones' => '',
                        'tiempo_entrega' => ''
                    ]
                ];
            }

            public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array {
                return [];
            }

            public function getProviderName(): string { return 'gemini'; }
            public function getModelName(): string { return 'gemini-3.6-flash'; }
            public function getPromptVersion(): string { return 'v1'; }
        };

        $job = new ParseAiCatalogChunkJob("chunk texto", $this->catalog->id);
        $job->handle($fakeAiProvider);

        $this->assertDatabaseHas('products', [
            'codigo' => 'AI-777',
            'catalog_id' => $this->catalog->id,
            'extraction_method' => 'ai',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-3.6-flash',
            'prompt_version' => 'v1',
            'parser_version' => 'v1',
        ]);
    }

    public function test_changing_config_versions_updates_metadata_on_upsert()
    {
        config(['services.ai.parser_version' => 'v2']);

        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'V1-MOD',
            'nombre' => 'Producto V1',
            'precio_divisa' => 10.00,
            'extraction_method' => 'text',
            'parser_version' => 'v1',
            'is_active' => true,
        ]);

        // Simular re-procesamiento con versión v2
        \Illuminate\Support\Facades\DB::table('products')->upsert([
            [
                'supplier_id' => $this->supplier->id,
                'catalog_id' => $this->catalog->id,
                'codigo' => 'V1-MOD',
                'nombre' => 'Producto V2 (Actualizado)',
                'precio_divisa' => 15.00,
                'extraction_method' => 'text',
                'parser_version' => 'v2',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ], ['supplier_id', 'codigo'], ['nombre', 'precio_divisa', 'parser_version', 'updated_at']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'codigo' => 'V1-MOD',
            'parser_version' => 'v2',
            'precio_divisa' => 15.00,
        ]);
    }
}
