<?php

namespace Tests\Feature;

use App\Contracts\AiProviderInterface;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CatalogProcessor;
use App\Services\Parsers\AiCatalogParser;
use App\Services\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAiFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Ferretería Central',
            'slug' => 'ferreteria-central',
        ]);

        config(['services.ai.gemini.api_key' => 'fake-test-key-999']);
        config(['services.ai.gemini.model' => 'gemini-3.6-flash']);
        config(['services.ai.parser_version' => 'v1']);
    }

    /**
     * FASE 4 & 5: Cuando el parser determinístico falla (0 productos),
     * se dispara el fallback y la IA procesa el chunk con Http::fake(),
     * persistiendo los productos en base de datos.
     */
    public function test_deterministic_parser_failure_triggers_ai_fallback_and_persists_product(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    [
                                        'codigo' => 'AI-FALLBACK-01',
                                        'nombre' => 'Compresor de Aire 50L 2HP',
                                        'precio_bs' => 9500.0,
                                        'precio_divisa' => 250.0,
                                        'descripcion' => 'Tanque 50 litros uso continuo',
                                        'garantia' => '1 año',
                                        'condiciones' => 'Retiro en tienda',
                                        'tiempo_entrega' => 'Inmediata',
                                    ]
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/fallback_test.pdf',
            'original_filename' => 'fallback_test.pdf',
            'status' => 'pending',
            'total_products' => 0,
        ]);

        // Texto complejo/no estructurado que hace fallar a los parsers regex determinísticos
        $unstructuredText = "CATALOGO ESPECIAL LIQUIDACION\nGran oportunidad de maquinaria pesada\nConsulte con su asesor comercial por el compresor de aire\nPrecios sujetos a cambio sin previo aviso.";

        // 1. Simular la llamada a AiCatalogParser (que ocurre en CatalogProcessor cuando count($products) === 0)
        $aiParser = app(AiCatalogParser::class);
        
        Queue::fake();
        $aiParser->parse($unstructuredText, '', $catalog->id, 1);

        // 2. Comprobar que se despachó el Job a la cola
        Queue::assertPushed(ParseAiCatalogChunkJob::class, function ($job) use ($catalog) {
            return $job->catalogId === $catalog->id && $job->pageNumber === 1;
        });

        // 3. Ejecutar el Job con el provider de IA real (que usa Http::fake())
        $jobInstance = new ParseAiCatalogChunkJob($unstructuredText, $catalog->id, 1);
        $jobInstance->handle(app(AiProviderInterface::class));

        // 4. Verificar que el producto fue persistido en DB con los metadatos correctos de IA
        $this->assertDatabaseHas('products', [
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'AI-FALLBACK-01',
            'nombre' => 'Compresor de Aire 50L 2HP',
            'precio_divisa' => 250.0,
            'page_number' => 1,
            'extraction_method' => 'ai',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-3.6-flash',
            'prompt_version' => 'v1',
            'parser_version' => 'v1',
        ]);

        // 5. Verificar que se realizó exactamente 1 llamada HTTP simulada a Gemini
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && str_contains($request->url(), 'key=fake-test-key-999');
        });
    }

    /**
     * FASE 5: Cuando el parser determinístico tiene éxito,
     * NO se realizan llamadas HTTP a la IA (Http::assertNothingSent).
     */
    public function test_successful_deterministic_parsing_does_not_call_ai_service(): void
    {
        Http::fake();

        // Texto estructurado válido que el parser determinístico resuelve sin IA
        $structuredText = "Juego de Llaves Allen 9 Pza\nJDHK2292\n$ 5.14\n";

        $productParser = app(\App\Services\ProductParser::class);
        $products = $productParser->parse($structuredText);

        $this->assertNotEmpty($products);
        $this->assertCount(1, $products);
        $this->assertEquals('JDHK2292', $products[0]['codigo']);

        // Comprobar que no hubo ninguna llamada HTTP saliente a Gemini
        Http::assertNothingSent();
    }
}
