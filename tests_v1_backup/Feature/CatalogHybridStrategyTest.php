<?php

namespace Tests\Feature;

use App\Contracts\AiProviderInterface;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CatalogOcrProcessor;
use App\Services\CatalogProcessor;
use App\Services\Parsers\CatalogParserFactory;
use App\Services\Parsers\Support\DeterministicExtractionEvaluator;
use App\Services\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogHybridStrategyTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Ferretería Híbrida',
            'slug' => 'ferreteria-hibrida',
        ]);

        config(['services.ai.gemini.api_key' => 'fake-test-key-hybrid']);
        config(['services.ai.gemini.model' => 'gemini-3.6-flash']);
        config(['services.ai.parser_version' => 'v1']);
    }

    /**
     * Caso A: Determinístico suficiente
     * PDF con texto estructurado -> Evaluator: SUFFICIENT -> Persiste producto en DB sin llamar a IA.
     */
    public function test_case_a_sufficient_deterministic_result_persists_without_calling_ai(): void
    {
        Http::fake();

        Storage::fake('local');
        $filePath = 'catalogs/structured.pdf';
        Storage::disk('local')->put($filePath, "Dummy content");

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'structured.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        $structuredText = "Juego de Llaves Allen 9 Pza\nJDHK2292\n$ 5.14\nJuego de Llaves Torx 8 Pza\nJDHK3281\n$ 6.47\n";
        $longText = $structuredText . str_repeat("\nEspecificaciones generales de la herramienta para uso profesional.", 10);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn($longText);

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            app(CatalogOcrProcessor::class),
            app(DeterministicExtractionEvaluator::class)
        );

        $processed = $processor->process($catalog);

        $this->assertEquals('completed', $processed->status);
        $this->assertEquals(2, $processed->total_products);
        $this->assertEquals(2, Product::where('catalog_id', $catalog->id)->count());

        $this->assertDatabaseHas('products', [
            'catalog_id' => $catalog->id,
            'codigo' => 'JDHK2292',
            'extraction_method' => 'text',
        ]);

        // Cero llamadas salientes a la API de IA
        Http::assertNothingSent();
    }

    /**
     * Caso B: Determinístico insuficiente
     * PDF con señales de productos pero parser regex falla -> Evaluator: INSUFFICIENT -> Fallback a IA con Http::fake().
     */
    public function test_case_b_insufficient_deterministic_result_triggers_ai_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    [
                                        'codigo' => 'AI-HYBRID-01',
                                        'nombre' => 'Compresor Industrial 100L 3HP',
                                        'precio_bs' => 15000.0,
                                        'precio_divisa' => 450.0,
                                        'descripcion' => 'Compresor trifásico',
                                        'garantia' => '1 año',
                                        'condiciones' => 'Flete incluido',
                                        'tiempo_entrega' => '24h',
                                    ]
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        Storage::fake('local');
        $filePath = 'catalogs/unstructured.pdf';
        Storage::disk('local')->put($filePath, "Dummy content");

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'unstructured.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        // Texto con precio y señales de especificaciones técnicas pero sin estructura de código estándar
        $unstructuredText = "COMPRESOR INDUSTRIAL 100L\n*POTENCIA: 3HP\n*VOLTAJE: 220V TRIFASICO\nPrecio promocional: $ 450.00\n";
        $longText = $unstructuredText . str_repeat("\nCondiciones comerciales sujetas a cambio sin previo aviso.", 10);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn($longText);

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            app(CatalogOcrProcessor::class),
            app(DeterministicExtractionEvaluator::class)
        );

        $processed = $processor->process($catalog);

        // Al ejecutarse el batch/job sincronamente en test, se completa con el producto de IA
        $freshCatalog = $catalog->fresh();
        $this->assertEquals('completed', $freshCatalog->status);
        $this->assertEquals(1, $freshCatalog->total_products);

        $this->assertDatabaseHas('products', [
            'catalog_id' => $catalog->id,
            'codigo' => 'AI-HYBRID-01',
            'precio_divisa' => 450.0,
            'extraction_method' => 'ai',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-3.6-flash',
        ]);
    }

    /**
     * Caso C: Página informativa / Portada
     * PDF sin productos ni precios -> Evaluator: NO_PRODUCT_PAGE -> Termina completed con 0 productos sin llamar a IA.
     */
    public function test_case_c_informational_page_completes_without_calling_ai(): void
    {
        Http::fake();

        Storage::fake('local');
        $filePath = 'catalogs/cover_info.pdf';
        Storage::disk('local')->put($filePath, "Dummy content");

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'cover_info.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        // Texto de certificado o términos sin códigos, sin precios y sin especificaciones técnicas
        $coverText = "AUTHORIZED DISTRIBUTOR CERTIFICATE\nThis certifies that the company is an authorized distributor.\nTerms and conditions apply.\nValidity 2026.\n";
        $longCoverText = $coverText . str_repeat("\nLegal notice and company disclaimer for business operations.", 10);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn($longCoverText);

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            app(CatalogOcrProcessor::class),
            app(DeterministicExtractionEvaluator::class)
        );

        $processed = $processor->process($catalog);

        $this->assertEquals('completed', $processed->status);
        $this->assertEquals(0, $processed->total_products);
        $this->assertEquals(0, Product::where('catalog_id', $catalog->id)->count());

        // Cero llamadas salientes a la IA
        Http::assertNothingSent();
    }
}
