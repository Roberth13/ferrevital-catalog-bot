<?php

namespace App\Jobs;

use App\Exceptions\CatalogParseException;
use App\Models\Catalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ParseAiCatalogChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        public readonly ?int $catalogId
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
     * @return void
     */
    public function handle()
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::error('Gemini API key no configurada.');
            return;
        }

        $prompt = "Eres un asistente experto en catálogos de ferretería.
Debes extraer los productos del siguiente texto, que provienen de una página de un PDF.
Devuelve los resultados estrictamente en formato JSON de acuerdo al responseSchema definido.

Reglas:
1. codigo: Si no hay código (SKU), usa vacio o ignora.
2. nombre: Nombre completo del producto.
3. precio_divisa: Extrae como número (solo la cifra, sin $). Si no hay, usa 0.
4. precio_bs: Extrae como número (solo la cifra, sin Bs). Si no hay, usa 0.
5. descripcion: Texto adicional si aplica.
6. garantia: Si hay texto sobre garantía en la página (ej: 'garantía de 1 año'), aplícala a los productos, sino vacio.
7. condiciones: Condiciones de proveedor (ej: 'Venta por bulto cerrado'), sino vacio.
8. tiempo_entrega: Tiempo de entrega si se menciona, sino vacio.

Ignora texto legal, basura OCR o encabezados que no sean productos.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt . "\n\nTEXTO A ANALIZAR:\n" . $this->chunk]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'codigo' => ['type' => 'STRING'],
                            'nombre' => ['type' => 'STRING'],
                            'precio_divisa' => ['type' => 'NUMBER'],
                            'precio_bs' => ['type' => 'NUMBER'],
                            'descripcion' => ['type' => 'STRING'],
                            'garantia' => ['type' => 'STRING'],
                            'condiciones' => ['type' => 'STRING'],
                            'tiempo_entrega' => ['type' => 'STRING']
                        ],
                        'required' => [
                            'codigo', 'nombre', 'precio_divisa', 'precio_bs',
                            'descripcion', 'garantia', 'condiciones', 'tiempo_entrega'
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(120)->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey, $payload);
        } catch (Exception $e) {
            Log::warning('Error de red al conectar con Gemini.', [
                'catalog_id' => $this->catalogId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        if (!$response->successful()) {
            $status = $response->status();
            
            // Ocultar API Key en logs
            $body = $response->body();
            
            Log::warning("Respuesta fallida de Gemini", [
                'catalog_id' => $this->catalogId,
                'attempt' => $this->attempts(),
                'status' => $status,
                'body' => substr($body, 0, 500) // Truncar si es muy largo
            ]);

            // Rate limits o saturación
            if ($status === 429 || $status === 503) {
                // Laravel interceptará esto y aplicará el backoff
                $response->throw(); 
            }

            // Para otros errores no recuperables, no lanzamos excepción HTTP para no reintentar a ciegas (depende del gusto, aquí marcamos como error permanente)
            throw new Exception("Error persistente de Gemini (Status $status)");
        }

        $data = $response->json();

        // Validar JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $rawBody = substr($response->body(), 0, 1000);
            Log::error('Respuesta de Gemini no es un JSON válido a nivel principal.', [
                'catalog_id' => $this->catalogId,
                'raw' => $rawBody
            ]);
            throw new CatalogParseException("Respuesta JSON inválida de Gemini.", $rawBody);
        }

        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
        $products = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $rawPayload = substr($jsonStr, 0, 1000);
            Log::error('El contenido extraído por Gemini no es un JSON válido.', [
                'catalog_id' => $this->catalogId,
                'raw' => $rawPayload
            ]);
            throw new CatalogParseException("El contenido extraído no es un JSON válido.", $rawPayload);
        }

        if (!is_array($products)) {
            $rawPayload = substr($jsonStr, 0, 1000);
            Log::error('El contenido extraído por Gemini no es un array.', [
                'catalog_id' => $this->catalogId,
                'raw' => $rawPayload
            ]);
            throw new CatalogParseException("El contenido extraído no es un array.", $rawPayload);
        }

        Log::info('Productos extraídos por Gemini con éxito.', [
            'catalog_id' => $this->catalogId,
            'count' => count($products)
        ]);

        if ($this->catalogId && count($products) > 0) {
            $this->persistProducts($products);
        }
    }

    /**
     * Persiste los productos en la base de datos (Upsert logic replicada)
     */
    private function persistProducts(array $products): void
    {
        Log::info('Persistiendo productos de Gemini en la base de datos.', [
            'catalog_id' => $this->catalogId,
            'count' => count($products)
        ]);

        DB::transaction(function () use ($products) {
            $now = now();
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
            $existingCodigos = DB::table('products')->whereIn('codigo', $codigos)->pluck('codigo')->toArray();
            $existingCodigosDict = array_flip($existingCodigos);

            $insertData = [];
            
            foreach ($uniqueProducts as $codigo => $product) {
                $isUpdate = isset($existingCodigosDict[$codigo]);
                
                $insertData[] = array_merge($product, [
                    'catalog_id' => $this->catalogId,
                    'is_active' => true,
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
                    ['codigo'], 
                    ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'garantia', 'condiciones', 'tiempo_entrega', 'descripcion'] 
                );
            }

            foreach (array_chunk($logsToInsert, 1000) as $logChunk) {
                DB::table('catalog_logs')->insert($logChunk);
            }
        });
    }
}
