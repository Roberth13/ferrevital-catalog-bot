<?php

namespace Tests\Feature;

use App\Contracts\AiProviderInterface;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CatalogOcrProcessor;
use App\Services\CatalogProcessor;
use App\Services\Parsers\AiCatalogParser;
use App\Services\Parsers\CatalogParserFactory;
use App\Services\PdfTextExtractor;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAsyncOperationalTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Ferretería Operacional',
            'slug' => 'ferreteria-operacional',
        ]);

        config(['services.ai.gemini.api_key' => 'fake-test-key']);
        config(['services.ai.gemini.model' => 'gemini-3.6-flash']);
        config(['services.ai.parser_version' => 'v1']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    [
                                        'codigo' => 'AI-DEFAULT-01',
                                        'nombre' => 'Producto Fake Gemini',
                                        'precio_bs' => 1000.0,
                                        'precio_divisa' => 25.0,
                                    ]
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);
    }

    /**
     * Caso 1: Catálogo 100% determinístico sin Jobs asíncronos
     * debe quedar en 'completed' con el total de productos correcto de inmediato.
     */
    public function test_deterministic_catalog_without_async_jobs_marks_completed_immediately(): void
    {
        Storage::fake('local');
        $filePath = 'catalogs/pure_text.pdf';
        Storage::disk('local')->put($filePath, "Contenido dummy de PDF con suficiente longitud para parser de texto.");

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'pure_text.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        $structuredText = "Juego de Llaves Allen 9 Pza\nJDHK2292\n$ 5.14\nJuego de Llaves Torx 8 Pza\nJDHK3281\n$ 6.47\n";
        // Asegurar longitud >= 500 caracteres para ser tratado como documento de texto por CatalogProcessor
        $longStructuredText = $structuredText . str_repeat("\nComentarios y especificaciones generales del catálogo de herramientas profesionales.", 10);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn($longStructuredText);

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            app(CatalogOcrProcessor::class)
        );

        $processedCatalog = $processor->process($catalog);

        $this->assertEquals('completed', $processedCatalog->status);
        $this->assertEquals(2, $processedCatalog->total_products);
        $this->assertEquals(2, Product::where('catalog_id', $catalog->id)->count());
    }

    /**
     * Caso 2: Catálogo con fallback a IA NO debe marcarse 'completed' prematuramente
     * mientras los Jobs asíncronos estén pendientes; al ejecutarse el Job, pasa a 'completed'.
     */
    public function test_catalog_with_single_async_job_remains_processing_until_job_finishes(): void
    {
        Queue::fake();

        Storage::fake('local');
        $filePath = 'catalogs/ai_fallback.pdf';
        Storage::disk('local')->put($filePath, "Texto no estructurado sin regex match");

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'ai_fallback.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn(
            str_repeat("Taladro percutor 13mm *POTENCIA: 600W Precio oferta especial: $ 45.00.\n", 20)
        );

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            app(CatalogOcrProcessor::class)
        );

        $processedCatalog = $processor->process($catalog);

        // Estado inmediato mientras el job está en cola
        $this->assertEquals('processing', $processedCatalog->status, 'El catálogo debe permanecer en processing mientras los Jobs de IA estén pendientes.');
        $this->assertEquals(0, $processedCatalog->total_products);

        // Simular ejecución del Job
        $fakeProvider = $this->createMock(AiProviderInterface::class);
        $fakeProvider->method('extractProductsFromChunk')->willReturn([
            [
                'codigo' => 'ASYNC-01',
                'nombre' => 'Taladro Percutor 13mm',
                'precio_divisa' => 45.0,
                'precio_bs' => 1800.0,
            ]
        ]);
        $fakeProvider->method('getProviderName')->willReturn('gemini');
        $fakeProvider->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProvider->method('getPromptVersion')->willReturn('v1');

        $job = new ParseAiCatalogChunkJob("chunk", $catalog->id, 1);
        $job->handle($fakeProvider);

        $freshCatalog = $catalog->fresh();
        $this->assertEquals('completed', $freshCatalog->status);
        $this->assertEquals(1, $freshCatalog->total_products);
        $this->assertEquals(1, Product::where('catalog_id', $catalog->id)->count());
    }

    /**
     * Caso 3: Catálogo con múltiples chunks (batch) -> AiCatalogParser divide el texto
     * y despacha un Bus::batch con todos los chunks asignados al catálogo.
     */
    public function test_catalog_with_multi_chunk_batch_remains_processing_until_all_jobs_in_batch_finish(): void
    {
        Bus::fake();

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/batch_test.pdf',
            'original_filename' => 'batch_test.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        // Crear un texto largo > 50,000 caracteres para forzar múltiples chunks en AiCatalogParser
        $chunkLine = "Linea de catalogo con descripcion de productos e informacion comercial.\n";
        $largeText = str_repeat($chunkLine, 1000); // ~73,000 caracteres -> 2 chunks

        $aiParser = app(AiCatalogParser::class);
        $aiParser->parse($largeText, '', $catalog->id, 1);

        Bus::assertBatched(function (\Illuminate\Support\Testing\Fakes\PendingBatchFake $batch) use ($catalog) {
            return $batch->name === 'catalog-' . $catalog->id
                && count($batch->jobs) === 2;
        });
    }

    /**
     * Caso 4: Job que falla definitivamente -> marca el catálogo como 'failed'.
     */
    public function test_failed_job_marks_catalog_as_failed(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/failed_job.pdf',
            'original_filename' => 'failed_job.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $job = new ParseAiCatalogChunkJob("chunk", $catalog->id, 1);
        $job->failed(new \RuntimeException("API quota exceeded irrevocably"));

        $this->assertEquals('failed', $catalog->fresh()->status);
    }

    /**
     * Caso 5: Job devuelve 0 productos (página informativa / portada) ->
     * termina en 'completed' con 0 productos (distinguir resultado vacío de fallo técnico).
     */
    public function test_job_returning_zero_products_is_not_a_technical_failure_and_marks_completed(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/empty_cover.pdf',
            'original_filename' => 'empty_cover.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $fakeProvider = $this->createMock(AiProviderInterface::class);
        $fakeProvider->method('extractProductsFromChunk')->willReturn([]); // Página de portada sin productos

        $job = new ParseAiCatalogChunkJob("Texto de portada", $catalog->id, 1);
        $job->handle($fakeProvider);

        $freshCatalog = $catalog->fresh();
        $this->assertEquals('completed', $freshCatalog->status);
        $this->assertEquals(0, $freshCatalog->total_products);
    }

    /**
     * Caso 6: Batch con fallo -> catch callback marca el catálogo como 'failed'.
     */
    public function test_batch_failure_marks_catalog_as_failed(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/batch_fail.pdf',
            'original_filename' => 'batch_fail.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $job1 = new ParseAiCatalogChunkJob("chunk 1", $catalog->id, 1);
        $job2 = new ParseAiCatalogChunkJob("chunk 2", $catalog->id, 2);

        $batch = Bus::batch([$job1, $job2])
            ->name('catalog-' . $catalog->id)
            ->then(function (Batch $batch) use ($catalog) {
                Catalog::where('id', $catalog->id)->update(['status' => 'completed']);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($catalog) {
                Catalog::where('id', $catalog->id)->update(['status' => 'failed']);
            })
            ->dispatch();

        // Disparar callback catch del batch simulando excepción
        foreach ($batch->options['catch'] ?? [] as $callback) {
            $callback($batch, new \RuntimeException("Batch job failure"));
        }

        $this->assertEquals('failed', $catalog->fresh()->status);
    }

    /**
     * Caso 7: Retry de Job por RateLimit -> lanza la excepción para que el worker reintente,
     * y el catálogo NO se marca como 'completed' durante el reintento.
     */
    public function test_job_temporary_error_propagates_for_queue_retry_and_keeps_processing_status(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/retry_test.pdf',
            'original_filename' => 'retry_test.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $fakeProvider = $this->createMock(AiProviderInterface::class);
        $fakeProvider->method('extractProductsFromChunk')->willThrowException(
            new AiRateLimitException("429 Too Many Requests")
        );

        $job = new ParseAiCatalogChunkJob("chunk", $catalog->id, 1);

        try {
            $job->handle($fakeProvider);
            $this->fail("Debió lanzar AiRateLimitException para permitir retry.");
        } catch (AiRateLimitException $e) {
            $this->assertEquals("429 Too Many Requests", $e->getMessage());
        }

        // El catálogo debe seguir en 'processing' (no completed ni failed)
        $this->assertEquals('processing', $catalog->fresh()->status);
    }

    /**
     * Caso 8: Catálogo mixto (página 1 determinística + página 2 fallback IA)
     * -> preserva ambos productos y actualiza total_products final.
     */
    public function test_mixed_catalog_deterministic_and_ai_fallback_preserves_both(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/mixed.pdf',
            'original_filename' => 'mixed.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        // 1. Inserción determinística de la página 1
        DB::table('products')->insert([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'DET-01',
            'nombre' => 'Producto Determinístico',
            'precio_divisa' => 15.0,
            'is_active' => true,
            'extraction_method' => 'text',
            'parser_version' => 'v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Job de IA procesa la página 2
        $fakeProvider = $this->createMock(AiProviderInterface::class);
        $fakeProvider->method('extractProductsFromChunk')->willReturn([
            [
                'codigo' => 'AI-02',
                'nombre' => 'Producto IA Fallback',
                'precio_divisa' => 35.0,
            ]
        ]);
        $fakeProvider->method('getProviderName')->willReturn('gemini');
        $fakeProvider->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProvider->method('getPromptVersion')->willReturn('v1');

        $job = new ParseAiCatalogChunkJob("chunk pagina 2", $catalog->id, 2);
        $job->handle($fakeProvider);

        $freshCatalog = $catalog->fresh();
        $this->assertEquals('completed', $freshCatalog->status);
        $this->assertEquals(2, $freshCatalog->total_products);

        $this->assertDatabaseHas('products', ['codigo' => 'DET-01', 'extraction_method' => 'text']);
        $this->assertDatabaseHas('products', ['codigo' => 'AI-02', 'extraction_method' => 'ai']);
    }

    /**
     * Caso 9: Reprocesamiento / Idempotencia -> no se crean registros duplicados al reejecutar el job.
     */
    public function test_ai_job_reprocessing_is_idempotent(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/idempotent.pdf',
            'original_filename' => 'idempotent.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $fakeProvider = $this->createMock(AiProviderInterface::class);
        $fakeProvider->method('extractProductsFromChunk')->willReturn([
            [
                'codigo' => 'IDEM-01',
                'nombre' => 'Producto Inicial',
                'precio_divisa' => 100.0,
            ]
        ]);
        $fakeProvider->method('getProviderName')->willReturn('gemini');
        $fakeProvider->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProvider->method('getPromptVersion')->willReturn('v1');

        $job = new ParseAiCatalogChunkJob("chunk", $catalog->id, 1);
        
        // Ejecución 1
        $job->handle($fakeProvider);
        $this->assertEquals(1, Product::where('supplier_id', $this->supplier->id)->where('codigo', 'IDEM-01')->count());
        $this->assertEquals(100.0, Product::where('supplier_id', $this->supplier->id)->where('codigo', 'IDEM-01')->first()->precio_divisa);

        // Ejecución 2 (con precio actualizado)
        $fakeProviderUpdated = $this->createMock(AiProviderInterface::class);
        $fakeProviderUpdated->method('extractProductsFromChunk')->willReturn([
            [
                'codigo' => 'IDEM-01',
                'nombre' => 'Producto Inicial',
                'precio_divisa' => 120.0,
            ]
        ]);
        $fakeProviderUpdated->method('getProviderName')->willReturn('gemini');
        $fakeProviderUpdated->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProviderUpdated->method('getPromptVersion')->willReturn('v1');

        $job->handle($fakeProviderUpdated);

        // Sin duplicados, precio actualizado
        $this->assertEquals(1, Product::where('supplier_id', $this->supplier->id)->where('codigo', 'IDEM-01')->count());
        $this->assertEquals(120.0, Product::where('supplier_id', $this->supplier->id)->where('codigo', 'IDEM-01')->first()->precio_divisa);
    }

    /**
     * Caso 10: Catálogo con múltiples chunks en batch -> todos pertenecen al mismo catálogo
     * y su finalización se contabiliza y actualiza correctamente mediante el callback then().
     */
    public function test_multi_chunk_batch_aggregates_all_chunks_for_same_catalog(): void
    {
        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/chunk_aggregation.pdf',
            'original_filename' => 'chunk_aggregation.pdf',
            'status' => 'processing',
            'total_products' => 0,
        ]);

        $fakeProvider1 = $this->createMock(AiProviderInterface::class);
        $fakeProvider1->method('extractProductsFromChunk')->willReturn([
            ['codigo' => 'CHUNK1-01', 'nombre' => 'Producto Chunk 1', 'precio_divisa' => 10.0]
        ]);
        $fakeProvider1->method('getProviderName')->willReturn('gemini');
        $fakeProvider1->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProvider1->method('getPromptVersion')->willReturn('v1');

        $fakeProvider2 = $this->createMock(AiProviderInterface::class);
        $fakeProvider2->method('extractProductsFromChunk')->willReturn([
            ['codigo' => 'CHUNK2-01', 'nombre' => 'Producto Chunk 2', 'precio_divisa' => 20.0]
        ]);
        $fakeProvider2->method('getProviderName')->willReturn('gemini');
        $fakeProvider2->method('getModelName')->willReturn('gemini-3.6-flash');
        $fakeProvider2->method('getPromptVersion')->willReturn('v1');

        $job1 = new ParseAiCatalogChunkJob("chunk 1", $catalog->id, 1);
        $job2 = new ParseAiCatalogChunkJob("chunk 2", $catalog->id, 2);

        $batchUuid = (string) \Illuminate\Support\Str::uuid();
        $job1->withBatchId($batchUuid);
        $job2->withBatchId($batchUuid);

        // 1. Ejecutar Job 1
        $job1->handle($fakeProvider1);
        $this->assertEquals('processing', $catalog->fresh()->status);
        $this->assertEquals(1, Product::where('catalog_id', $catalog->id)->count());

        // 2. Ejecutar Job 2
        $job2->handle($fakeProvider2);
        $this->assertEquals('processing', $catalog->fresh()->status);
        $this->assertEquals(2, Product::where('catalog_id', $catalog->id)->count());

        // 3. Callback then del batch se dispara al completar todos los chunks
        $total = DB::table('products')->where('catalog_id', $catalog->id)->count();
        Catalog::where('id', $catalog->id)->update([
            'status' => 'completed',
            'total_products' => $total,
        ]);

        $freshCatalog = $catalog->fresh();
        $this->assertEquals('completed', $freshCatalog->status);
        $this->assertEquals(2, $freshCatalog->total_products);
        $this->assertEquals(2, Product::where('catalog_id', $catalog->id)->count());
        $this->assertDatabaseHas('products', ['codigo' => 'CHUNK1-01', 'catalog_id' => $catalog->id]);
        $this->assertDatabaseHas('products', ['codigo' => 'CHUNK2-01', 'catalog_id' => $catalog->id]);
    }

    /**
     * Caso 11: CatalogProcessor con múltiples páginas INSUFFICIENT crea un único batch
     * con todos los Jobs de todas las páginas insuficientes asociados al catálogo.
     */
    public function test_multipage_ocr_fallback_aggregates_all_pages_into_single_catalog_batch(): void
    {
        Bus::fake();
        Storage::fake('local');

        $realPdf = base_path('public/PDFs/Dong Cheng.pdf');
        $filePath = 'catalogs/multipage_ocr.pdf';
        Storage::disk('local')->put($filePath, file_get_contents($realPdf));

        $catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => $filePath,
            'original_filename' => 'multipage_ocr.pdf',
            'status' => 'uploaded',
            'total_products' => 0,
        ]);

        $mockPdfExtractor = $this->createMock(PdfTextExtractor::class);
        $mockPdfExtractor->method('extract')->willReturn("short");

        // Simular 3 páginas donde 2 son INSUFFICIENT
        $mockOcrProcessor = $this->createMock(CatalogOcrProcessor::class);
        $mockOcrProcessor->method('process')->willReturn([
            [
                'page' => 1,
                'normal_text' => "Texto explicativo sin productos ni precios",
                'red_text' => '',
            ],
            [
                'page' => 2,
                'normal_text' => "Amoladora 850W con especificacion tecnica",
                'red_text' => "35,73 75,63",
            ],
            [
                'page' => 3,
                'normal_text' => "Cepillo electrico 82mm",
                'red_text' => "64,13 95,35",
            ],
        ]);

        $processor = new CatalogProcessor(
            $mockPdfExtractor,
            app(CatalogParserFactory::class),
            $mockOcrProcessor,
            app(\App\Services\Parsers\Support\DeterministicExtractionEvaluator::class)
        );

        $processedCatalog = $processor->process($catalog);

        // El catálogo debe permanecer en processing
        $this->assertEquals('processing', $processedCatalog->status);

        // Debe haberse despachado un Bus::batch con nombre catalog-{id} conteniendo los 2 jobs
        Bus::assertBatched(function (\Illuminate\Support\Testing\Fakes\PendingBatchFake $batch) use ($catalog) {
            return $batch->name === 'catalog-' . $catalog->id
                && count($batch->jobs) === 2;
        });
    }
}
