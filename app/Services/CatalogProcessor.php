<?php

namespace App\Services;

use App\Models\Catalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use App\Services\Parsers\CatalogParserFactory;

class CatalogProcessor
{
    public function __construct(
        private readonly PdfTextExtractor $pdfTextExtractor,
        private readonly CatalogParserFactory $parserFactory,
        private readonly CatalogOcrProcessor $ocrProcessor,
    ) {
    }

    public function process(Catalog $catalog): Catalog
    {
        $catalog->update([
            'status' => 'processing',
        ]);

        try {
            $path = Storage::disk('local')->path($catalog->filename);

            $text = $this->pdfTextExtractor->extract($path);

            $products = [];

            // Si el texto es muy corto, asumimos que es un PDF de imágenes
            if (strlen(trim($text)) < 500) {
                // Usamos pdfinfo (Poppler) para contar páginas sin cargar memoria
                $binPath = config('services.poppler.bin_path', 'C:\\Tools\\poppler\\Library\\bin');
                $executable = str_replace('/', '\\', $binPath . '\\pdfinfo.exe');
                
                $process = new \Symfony\Component\Process\Process([$executable, $path]);
                $process->run();
                
                $totalPages = 1;
                if ($process->isSuccessful()) {
                    if (preg_match('/Pages:\s+(\d+)/i', $process->getOutput(), $matches)) {
                        $totalPages = (int) $matches[1];
                    }
                } else {
                    throw new RuntimeException("Error al contar páginas con pdfinfo: " . $process->getErrorOutput());
                }

                $pagesData = $this->ocrProcessor->process($path, $totalPages);
                
                foreach ($pagesData as $pageData) {
                    if (empty($pageData['normal_text']) && empty($pageData['red_text'])) {
                        continue;
                    }
                    
                    echo "=> Analizando página {$pageData['page']}...\n";
                    
                    // Detectar parser en base al texto normal de la página (ej: 'Dong Cheng')
                    $pageParser = $this->parserFactory->make($pageData['normal_text']);
                    $pageProducts = $pageParser->parse($pageData['normal_text'], $pageData['red_text'] ?? '');
                    
                    if (count($pageProducts) === 0) {
                        echo "   - Parser Regex falló, enviando página {$pageData['page']} a la Inteligencia Artificial...\n";
                        $aiParser = app(\App\Services\Parsers\AiCatalogParser::class);
                        $pageProducts = $aiParser->parse($pageData['normal_text'], $pageData['red_text'] ?? '', $catalog->id);
                    }
                    
                    echo "   - Productos extraídos en página {$pageData['page']}: " . count($pageProducts) . "\n";
                    $products = array_merge($products, $pageProducts);
                }
            } else {
                echo "=> Procesando documento entero (texto extraíble)...\n";
                // Flujo normal de texto
                $parser = $this->parserFactory->make($text);
                $products = $parser->parse($text);
                
                if (count($products) === 0) {
                    echo "   - Parser Regex falló, enviando el texto a la Inteligencia Artificial...\n";
                    $aiParser = app(\App\Services\Parsers\AiCatalogParser::class);
                    $products = $aiParser->parse($text, '', $catalog->id);
                }
                echo "   - Total productos extraídos: " . count($products) . "\n";
            }

            echo "=> Guardando datos en la base de datos...\n";
            DB::transaction(function () use ($catalog, $products) {
                $now = now();
                
                // 1. Agrupar por código (eliminar duplicados dentro del mismo PDF)
                $uniqueProducts = [];
                $logsToInsert = [];
                
                foreach ($products as $product) {
                    $codigo = $product['codigo'] ?? null;
                    
                    if (empty($codigo)) {
                        $logsToInsert[] = [
                            'catalog_id' => $catalog->id,
                            'codigo' => null,
                            'status' => 'failed',
                            'message' => 'Producto sin código (SKU vacío).',
                            'raw_data' => json_encode($product),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        continue;
                    }
                    
                    if (isset($uniqueProducts[$codigo])) {
                        $logsToInsert[] = [
                            'catalog_id' => $catalog->id,
                            'codigo' => $codigo,
                            'status' => 'duplicated',
                            'message' => 'Duplicado ignorado en el PDF.',
                            'raw_data' => json_encode($product),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        continue;
                    }
                    
                    $uniqueProducts[$codigo] = $product;
                }

                // 2. Determinar si los códigos existen en DB para registrar created vs updated
                $codigos = array_keys($uniqueProducts);
                $existingCodigos = DB::table('products')->whereIn('codigo', $codigos)->pluck('codigo')->toArray();
                $existingCodigosDict = array_flip($existingCodigos);

                $insertData = [];
                
                foreach ($uniqueProducts as $codigo => $product) {
                    $isUpdate = isset($existingCodigosDict[$codigo]);
                    
                    $insertData[] = array_merge($product, [
                        'catalog_id' => $catalog->id,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $logsToInsert[] = [
                        'catalog_id' => $catalog->id,
                        'codigo' => $codigo,
                        'status' => $isUpdate ? 'updated' : 'created',
                        'message' => $isUpdate ? 'Precio y nombre actualizados.' : 'Nuevo producto creado.',
                        'raw_data' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 3. Upsert de productos (Actualiza los campos especificados si el código existe)
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::table('products')->upsert(
                        $chunk, 
                        ['codigo'], // Unique columns
                        ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at'] // Columns to update on conflict
                    );
                }

                // 4. Insertar los Logs
                foreach (array_chunk($logsToInsert, 1000) as $logChunk) {
                    DB::table('catalog_logs')->insert($logChunk);
                }

                $catalog->update([
                    'total_products' => count($insertData),
                    'status' => 'completed',
                ]);
            });

            return $catalog->fresh();

        } catch (\Throwable $e) {
            $catalog->update([
                'status' => 'failed',
            ]);

            throw new RuntimeException(
                "No se pudo procesar el catálogo #{$catalog->id}: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}