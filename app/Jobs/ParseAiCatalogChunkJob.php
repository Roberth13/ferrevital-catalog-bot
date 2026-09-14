<?php

namespace App\Jobs;

use App\Contracts\AiProviderInterface;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;
use App\Exceptions\Ai\AiTemporaryException;
use App\Exceptions\CatalogParseException;
use App\Models\Catalog;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParseAiCatalogChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos.
     */
    public $tries = 6;

    /**
     * @param string $chunk El texto a procesar.
     * @param int|null $catalogId ID del catálogo, si existe.
     */
    public function __construct(
        public readonly string $chunk,
        public readonly ?int $catalogId,
        public readonly ?int $pageNumber = null
    ) {
    }

    /**
     * Retraso de reintento exponencial.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 10, 20, 40, 80, 160];
    }

    /**
     * Execute the job.
     *
     * @param AiProviderInterface $aiProvider
     * @return void
     */
    public function handle(AiProviderInterface $aiProvider): void
    {
        try {
            $products = $aiProvider->extractProductsFromChunk($this->chunk);
        } catch (AiRateLimitException | AiTemporaryException $e) {
            Log::warning('Fallo temporal o limite de cuota en IA.', [
                'catalog_id' => $this->catalogId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);
            throw $e; // Laravel interceptará esto y aplicará el backoff
        } catch (AiResponseParseException $e) {
            Log::error('Respuesta de IA no es un JSON válido.', [
                'catalog_id' => $this->catalogId,
                'raw' => $e->rawResponse
            ]);
            throw new CatalogParseException($e->getMessage(), $e->rawResponse);
        }

        Log::info('Productos extraídos por IA con éxito.', [
            'catalog_id' => $this->catalogId,
            'count' => count($products)
        ]);

        if ($this->catalogId && count($products) > 0) {
            $this->persistProducts($products, $aiProvider);
        }

        if ($this->catalogId && empty($this->batchId) && ($this->batch() === null)) {
            $total = DB::table('products')->where('catalog_id', $this->catalogId)->count();
            \App\Models\Catalog::where('id', $this->catalogId)->update([
                'status' => 'completed',
                'total_products' => $total,
            ]);
        }
    }

    /**
     * Persiste los productos en la base de datos (Upsert logic por supplier_id y codigo)
     */
    private function persistProducts(array $products, AiProviderInterface $aiProvider): void
    {
        Log::info('Persistiendo productos de IA en la base de datos.', [
            'catalog_id' => $this->catalogId,
            'count' => count($products)
        ]);

        DB::transaction(function () use ($products, $aiProvider) {
            $now = now();
            $catalog = $this->catalogId ? Catalog::find($this->catalogId) : null;
            $supplierId = $catalog?->supplier_id ?? \App\Models\Supplier::getGenericSupplierId();

            $uniqueProducts = [];
            $logsToInsert = [];
            
            foreach ($products as $product) {
                $codigo = $product['codigo'] ?? null;
                
                if (empty($codigo)) {
                    $logsToInsert[] = [
                        'catalog_id' => $this->catalogId,
                        'codigo' => null,
                        'status' => 'failed',
                        'message' => 'Producto sin código (SKU vacío) ignorado por AI Job.',
                        'raw_data' => json_encode($product),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    continue;
                }
                
                if (isset($uniqueProducts[$codigo])) {
                    $logsToInsert[] = [
                        'catalog_id' => $this->catalogId,
                        'codigo' => $codigo,
                        'status' => 'duplicated',
                        'message' => 'Duplicado ignorado en el chunk (AI Job).',
                        'raw_data' => json_encode($product),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    continue;
                }
                
                $uniqueProducts[$codigo] = $product;
            }

            $codigos = array_keys($uniqueProducts);
            $existingCodigos = DB::table('products')
                ->where('supplier_id', $supplierId)
                ->whereIn('codigo', $codigos)
                ->pluck('codigo')
                ->toArray();
            $existingCodigosDict = array_flip($existingCodigos);

            $insertData = [];
            $parserVersion = config('services.ai.parser_version', 'v1');

            foreach ($uniqueProducts as $codigo => $product) {
                $isUpdate = isset($existingCodigosDict[$codigo]);
                
                $insertData[] = array_merge($product, [
                    'supplier_id' => $supplierId,
                    'catalog_id' => $this->catalogId,
                    'page_number' => $this->pageNumber,
                    'is_active' => true,
                    'extraction_method' => 'ai',
                    'ai_provider' => $aiProvider->getProviderName(),
                    'ai_model' => $aiProvider->getModelName(),
                    'prompt_version' => $aiProvider->getPromptVersion(),
                    'parser_version' => $parserVersion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $logsToInsert[] = [
                    'catalog_id' => $this->catalogId,
                    'codigo' => $codigo,
                    'status' => $isUpdate ? 'updated' : 'created',
                    'message' => $isUpdate ? 'Precio y nombre actualizados (AI Job).' : 'Nuevo producto creado (AI Job).',
                    'raw_data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::table('products')->upsert(
                    $chunk, 
                    ['supplier_id', 'codigo'], 
                    ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'garantia', 'condiciones', 'tiempo_entrega', 'descripcion', 'extraction_method', 'ai_provider', 'ai_model', 'prompt_version', 'parser_version', 'page_number'] 
                );
            }

            foreach (array_chunk($logsToInsert, 1000) as $logChunk) {
                DB::table('catalog_logs')->insert($logChunk);
            }
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ParseAiCatalogChunkJob falló definitivamente.', [
            'catalog_id' => $this->catalogId,
            'page_number' => $this->pageNumber,
            'error' => $exception?->getMessage()
        ]);

        if ($this->catalogId && empty($this->batchId) && ($this->batch() === null)) {
            \App\Models\Catalog::where('id', $this->catalogId)->update([
                'status' => 'failed',
            ]);
        }
    }
}
