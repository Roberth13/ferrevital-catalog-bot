<?php

namespace Tests\Unit;

use App\Contracts\AiProviderInterface;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;
use App\Exceptions\Ai\AiTemporaryException;
use App\Exceptions\CatalogParseException;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParseAiCatalogChunkJobDetailedTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Dong Cheng Tools',
            'slug' => 'dong-cheng-tools',
        ]);

        $this->catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/dong_cheng.pdf',
            'original_filename' => 'Dong Cheng.pdf',
            'status' => 'processing',
        ]);

        config(['services.ai.parser_version' => 'v1']);
    }

    private function createMockProvider(array $productsReturn): AiProviderInterface
    {
        return new class($productsReturn) implements AiProviderInterface {
            public function __construct(private array $products) {}
            public function extractProductsFromChunk(string $chunkText): array { return $this->products; }
            public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array { return []; }
            public function getProviderName(): string { return 'gemini'; }
            public function getModelName(): string { return 'gemini-3.6-flash'; }
            public function getPromptVersion(): string { return 'v1'; }
        };
    }

    /**
     * A & B. Chunk válido con varios productos -> persiste todos correctamente.
     */
    public function test_job_persists_all_valid_products_from_ai(): void
    {
        $products = [
            [
                'codigo' => 'DCPL208',
                'nombre' => 'Atornillador de Impacto 20V',
                'precio_divisa' => 83.48,
                'precio_bs' => 3100.0,
                'descripcion' => 'Torque 208 Nm',
                'garantia' => '1 año',
                'condiciones' => 'Caja plástica',
                'tiempo_entrega' => 'Inmediata',
            ],
            [
                'codigo' => 'DCJZ1202',
                'nombre' => 'Taladro Atornillador 12V',
                'precio_divisa' => 45.75,
                'precio_bs' => 1700.0,
                'descripcion' => 'Batería 12V Max',
                'garantia' => '1 año',
                'condiciones' => 'Blister',
                'tiempo_entrega' => 'Inmediata',
            ],
        ];

        $provider = $this->createMockProvider($products);
        $job = new ParseAiCatalogChunkJob("Texto chunk", $this->catalog->id, 4);
        $job->handle($provider);

        $this->assertDatabaseHas('products', [
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'DCPL208',
            'nombre' => 'Atornillador de Impacto 20V',
            'precio_divisa' => 83.48,
            'page_number' => 4,
            'extraction_method' => 'ai',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-3.6-flash',
            'prompt_version' => 'v1',
            'parser_version' => 'v1',
        ]);

        $this->assertDatabaseHas('products', [
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'DCJZ1202',
            'nombre' => 'Taladro Atornillador 12V',
            'precio_divisa' => 45.75,
            'page_number' => 4,
            'extraction_method' => 'ai',
        ]);

        $this->assertEquals(2, Product::where('catalog_id', $this->catalog->id)->count());
    }

    /**
     * C. Producto sin código (SKU vacío) -> se ignora y se registra en logs como 'failed'.
     */
    public function test_job_ignores_product_without_sku_and_logs_failed(): void
    {
        $products = [
            [
                'codigo' => '',
                'nombre' => 'Producto Sin Código',
                'precio_divisa' => 10.0,
            ],
            [
                'codigo' => null,
                'nombre' => 'Otro Sin Código',
                'precio_divisa' => 20.0,
            ],
            [
                'codigo' => 'VALID-SKU',
                'nombre' => 'Producto Válido',
                'precio_divisa' => 30.0,
            ],
        ];

        $provider = $this->createMockProvider($products);
        $job = new ParseAiCatalogChunkJob("Texto chunk", $this->catalog->id, 2);
        $job->handle($provider);

        $this->assertDatabaseMissing('products', ['nombre' => 'Producto Sin Código']);
        $this->assertDatabaseMissing('products', ['nombre' => 'Otro Sin Código']);
        $this->assertDatabaseHas('products', ['codigo' => 'VALID-SKU']);

        $this->assertDatabaseHas('catalog_logs', [
            'catalog_id' => $this->catalog->id,
            'status' => 'failed',
        ]);
    }

    /**
     * D. Productos duplicados en el mismo chunk -> deduplicados, uno insertado y uno registrado como 'duplicated'.
     */
    public function test_job_deduplicates_products_in_same_chunk(): void
    {
        $products = [
            [
                'codigo' => 'DUP-001',
                'nombre' => 'Primer Registro',
                'precio_divisa' => 50.0,
            ],
            [
                'codigo' => 'DUP-001',
                'nombre' => 'Segundo Registro Duplicado',
                'precio_divisa' => 50.0,
            ],
        ];

        $provider = $this->createMockProvider($products);
        $job = new ParseAiCatalogChunkJob("Texto chunk", $this->catalog->id, 5);
        $job->handle($provider);

        $this->assertEquals(1, Product::where('codigo', 'DUP-001')->count());
        $this->assertDatabaseHas('catalog_logs', [
            'catalog_id' => $this->catalog->id,
            'codigo' => 'DUP-001',
            'status' => 'duplicated',
        ]);
    }

    /**
     * E. Producto con código existente en DB -> actualiza en lugar de duplicar (Upsert por supplier_id, codigo).
     */
    public function test_job_updates_existing_product_instead_of_duplicating(): void
    {
        Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'EXISTING-01',
            'nombre' => 'Nombre Antiguo',
            'precio_divisa' => 100.0,
            'extraction_method' => 'ocr',
            'parser_version' => 'v0',
            'is_active' => true,
        ]);

        $products = [
            [
                'codigo' => 'EXISTING-01',
                'nombre' => 'Nombre Actualizado por IA',
                'precio_divisa' => 125.0,
                'precio_bs' => 0,
                'descripcion' => 'Nueva desc',
                'garantia' => '2 años',
                'condiciones' => '',
                'tiempo_entrega' => '',
            ]
        ];

        $provider = $this->createMockProvider($products);
        $job = new ParseAiCatalogChunkJob("Texto chunk", $this->catalog->id, 8);
        $job->handle($provider);

        $this->assertEquals(1, Product::where('supplier_id', $this->supplier->id)->where('codigo', 'EXISTING-01')->count());

        $this->assertDatabaseHas('products', [
            'supplier_id' => $this->supplier->id,
            'codigo' => 'EXISTING-01',
            'nombre' => 'Nombre Actualizado por IA',
            'precio_divisa' => 125.0,
            'extraction_method' => 'ai',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-3.6-flash',
            'parser_version' => 'v1',
            'page_number' => 8,
        ]);

        $this->assertDatabaseHas('catalog_logs', [
            'catalog_id' => $this->catalog->id,
            'codigo' => 'EXISTING-01',
            'status' => 'updated',
        ]);
    }

    /**
     * F & G. Error temporal/Rate limit (429, 503) -> Job relanza excepción para que la cola aplique reintento y backoff.
     */
    public function test_job_rethrows_recoverable_exceptions_for_queue_retry(): void
    {
        $rateLimitProvider = new class implements AiProviderInterface {
            public function extractProductsFromChunk(string $chunkText): array {
                throw new AiRateLimitException("Cuota excedida 429", 429);
            }
            public function rankProductsByValueForMoney(array $d, string $q): array { return []; }
            public function getProviderName(): string { return 'gemini'; }
            public function getModelName(): string { return 'gemini-3.6-flash'; }
            public function getPromptVersion(): string { return 'v1'; }
        };

        $job = new ParseAiCatalogChunkJob("Texto", $this->catalog->id);

        $this->expectException(AiRateLimitException::class);
        $job->handle($rateLimitProvider);
    }

    /**
     * H. Error no recuperable (JSON inválido) -> lanza CatalogParseException y no corrompe la DB.
     */
    public function test_job_throws_catalog_parse_exception_on_unrecoverable_json_error(): void
    {
        $badJsonProvider = new class implements AiProviderInterface {
            public function extractProductsFromChunk(string $chunkText): array {
                throw new AiResponseParseException("JSON Malformado", "Raw invalid content");
            }
            public function rankProductsByValueForMoney(array $d, string $q): array { return []; }
            public function getProviderName(): string { return 'gemini'; }
            public function getModelName(): string { return 'gemini-3.6-flash'; }
            public function getPromptVersion(): string { return 'v1'; }
        };

        $job = new ParseAiCatalogChunkJob("Texto", $this->catalog->id);

        $this->expectException(CatalogParseException::class);
        $job->handle($badJsonProvider);

        $this->assertEquals(0, Product::where('catalog_id', $this->catalog->id)->count());
    }

    /**
     * Retry policy: Verifica configuración de tries y backoff del Job.
     */
    public function test_job_declares_six_tries_and_exponential_backoff(): void
    {
        $job = new ParseAiCatalogChunkJob("Texto", $this->catalog->id);

        $this->assertEquals(6, $job->tries);
        $this->assertEquals([5, 10, 20, 40, 80, 160], $job->backoff());
    }
}
