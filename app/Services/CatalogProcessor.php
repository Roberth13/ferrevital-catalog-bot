<?php

namespace App\Services;

use App\Models\Catalog;
use App\Services\Parsers\CatalogParserFactory;
use App\Services\Parsers\Support\DeterministicExtractionEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CatalogProcessor
{
    private readonly DeterministicExtractionEvaluator $evaluator;

    public function __construct(
        private readonly PdfTextExtractor $pdfTextExtractor,
        private readonly CatalogParserFactory $parserFactory,
        private readonly CatalogOcrProcessor $ocrProcessor,
        ?DeterministicExtractionEvaluator $evaluator = null,
    ) {
        $this->evaluator = $evaluator ?? new DeterministicExtractionEvaluator();
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
            $asyncJobs = [];

            // Si el texto es muy corto, asumimos que es un PDF de imágenes
            if (strlen(trim($text)) < 500) {
                // Usamos pdfinfo (Poppler) para contar páginas sin cargar memoria
                $isWindows = PHP_OS_FAMILY === 'Windows';
                $binPath = config('services.poppler.bin_path');
                $binaryName = $isWindows ? 'pdfinfo.exe' : 'pdfinfo';

                if (!empty($binPath)) {
                    $executable = rtrim($binPath, '/\\') . DIRECTORY_SEPARATOR . $binaryName;
                } else {
                    $executable = $isWindows ? 'C:\\Tools\\poppler\\Library\\bin\\pdfinfo.exe' : 'pdfinfo';
                }
                
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
                $aiParser = app(\App\Services\Parsers\AiCatalogParser::class);
                
                foreach ($pagesData as $pageData) {
                    if (empty($pageData['normal_text']) && empty($pageData['red_text'])) {
                        continue;
                    }
                    
                    echo "=> Analizando página {$pageData['page']}...\n";
                    
                    // Detectar parser en base al texto normal de la página (ej: 'Dong Cheng')
                    $pageParser = $this->parserFactory->make($pageData['normal_text']);
                    $pageProducts = $pageParser->parse($pageData['normal_text'], $pageData['red_text'] ?? '');
                    
                    $evaluation = $this->evaluator->evaluate($pageProducts, $pageData['normal_text'], $pageData['red_text'] ?? '');

                    if ($evaluation === DeterministicExtractionEvaluator::SUFFICIENT) {
                        // Agregar metadatos de extracción por página y OCR
                        foreach ($pageProducts as &$p) {
                            $p['page_number'] = $pageData['page'];
                            $p['extraction_method'] = 'ocr';
                            $p['parser_version'] = config('services.ai.parser_version', 'v1');
                        }
                        unset($p);
                        $products = array_merge($products, $pageProducts);
                    } elseif ($evaluation === DeterministicExtractionEvaluator::INSUFFICIENT) {
                        echo "   - Parser Regex insuficiente, enviando página {$pageData['page']} a la Inteligencia Artificial...\n";
                        $pageJobs = $aiParser->createJobs($pageData['normal_text'], $pageData['red_text'] ?? '', $catalog->id, $pageData['page']);
                        $asyncJobs = array_merge($asyncJobs, $pageJobs);
                    } else {
                        echo "   - Página {$pageData['page']} clasificada como informativa/sin productos.\n";
                    }
                    
                    echo "   - Productos extraídos en página {$pageData['page']}: " . count($pageProducts) . "\n";
                }
            } else {
                echo "=> Procesando documento entero (texto extraíble)...\n";
                // Flujo normal de texto
                $parser = $this->parserFactory->make($text);
                $docProducts = $parser->parse($text);

                $evaluation = $this->evaluator->evaluate($docProducts, $text);

                if ($evaluation === DeterministicExtractionEvaluator::SUFFICIENT) {
                    // Agregar metadatos de extracción determinística por texto
                    foreach ($docProducts as &$p) {
                        $p['extraction_method'] = 'text';
                        $p['parser_version'] = config('services.ai.parser_version', 'v1');
                    }
                    unset($p);
                    $products = $docProducts;
                } elseif ($evaluation === DeterministicExtractionEvaluator::INSUFFICIENT) {
                    echo "   - Parser Regex insuficiente, enviando el texto a la Inteligencia Artificial...\n";
                    $aiParser = app(\App\Services\Parsers\AiCatalogParser::class);
                    $docJobs = $aiParser->createJobs($text, '', $catalog->id);
                    $asyncJobs = array_merge($asyncJobs, $docJobs);
                } else {
                    echo "   - Documento clasificado como informativo/sin productos.\n";
                }
                echo "   - Total productos extraídos: " . count($products) . "\n";
            }

            echo "=> Guardando datos en la base de datos...\n";
            DB::transaction(function () use ($catalog, $products, $asyncJobs) {
                $now = now();
                $supplierId = $catalog->supplier_id ?? \App\Models\Supplier::getGenericSupplierId(); // Default to Generic Supplier if unassigned
                
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

                // 2. Determinar si los códigos existen en DB para el proveedor para registrar created vs updated
                $codigos = array_keys($uniqueProducts);
                $existingCodigos = DB::table('products')
                    ->where('supplier_id', $supplierId)
                    ->whereIn('codigo', $codigos)
                    ->pluck('codigo')
                    ->toArray();
                $existingCodigosDict = array_flip($existingCodigos);

                $insertData = [];
                
                foreach ($uniqueProducts as $codigo => $product) {
                    $isUpdate = isset($existingCodigosDict[$codigo]);
                    
                    $insertData[] = array_merge([
                        'extraction_method' => 'text',
                        'ai_provider' => null,
                        'ai_model' => null,
                        'prompt_version' => null,
                        'parser_version' => config('services.ai.parser_version', 'v1'),
                    ], $product, [
                        'supplier_id' => $supplierId,
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

                // 3. Upsert de productos por (supplier_id, codigo)
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::table('products')->upsert(
                        $chunk, 
                        ['supplier_id', 'codigo'], // Unique columns per supplier
                        ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'extraction_method', 'ai_provider', 'ai_model', 'prompt_version', 'parser_version', 'page_number'] // Columns to update on conflict
                    );
                }

                // 4. Insertar los Logs
                foreach (array_chunk($logsToInsert, 1000) as $logChunk) {
                    DB::table('catalog_logs')->insert($logChunk);
                }

                $catalog->update([
                    'total_products' => count($insertData),
                    'status' => !empty($asyncJobs) ? 'processing' : 'completed',
                ]);
            });

            if (!empty($asyncJobs) && $catalog->id) {
                \Illuminate\Support\Facades\Bus::batch($asyncJobs)
                    ->name('catalog-' . $catalog->id)
                    ->then(function (\Illuminate\Bus\Batch $batch) use ($catalog) {
                        $total = DB::table('products')->where('catalog_id', $catalog->id)->count();
                        Catalog::where('id', $catalog->id)->update([
                            'status' => 'completed',
                            'total_products' => $total,
                        ]);
                    })
                    ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($catalog) {
                        Catalog::where('id', $catalog->id)->update([
                            'status' => 'failed',
                        ]);
                    })
                    ->dispatch();
            }

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