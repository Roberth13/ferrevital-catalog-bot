<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Contracts\AiProviderInterface;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;
use App\Exceptions\Ai\AiTemporaryException;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Services\Ai\Drivers\GeminiAiDriver;
use App\Services\Parsers\Support\DeterministicExtractionEvaluator;
use App\Services\PdfTextExtractor;
use App\Services\ProductParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------
// CLI Arguments and Safety Mode
// ---------------------------------------------------------
$options = getopt('', ['live', 'dry-run']);
$isLive = isset($options['live']);

echo "=================================================================\n";
echo " ETAPA 9 — VALIDACIÓN REAL DEL FALLBACK IA + FACTORY HÍBRIDA\n";
echo " Mode: " . ($isLive ? "REAL LIVE AI EXECUTION" : "DRY RUN (Simulated / Safe Mode)") . "\n";
echo "=================================================================\n\n";

$popplerBin = config('services.poppler.bin_path', 'C:\\Tools\\poppler\\Library\\bin');
$pdftotext = $popplerBin . '\\pdftotext.exe';

$dcJsonPath = 'C:/Users/rober/.gemini/antigravity-ide/brain/8cff0ba5-c805-492b-b043-57fe0fc49612/scratch/e2e_report_raw.json';
$dcRawData = json_decode(file_get_contents($dcJsonPath), true);
$jadeverPath = base_path('public/PDFs/Catalogo Jadever 04-05-2026.pdf');

$targetPages = [
    'Dong Cheng' => [8, 14, 15],
    'Jadever' => [44, 53, 63, 196, 205, 214, 215, 294],
];

// Ground Truth verified 100% against PDF text and layouts
$groundTruth = [
    'Dong Cheng' => [
        8 => [
            'expected_products_count' => 2,
            'products' => [
                ['codigo' => 'DMR02-350', 'nombre' => 'ESMERIL RECTO', 'precio_divisa' => 35.73],
                ['codigo' => 'DSJ02-25', 'nombre' => 'ESMERIL RECTO', 'precio_divisa' => 75.63],
            ]
        ],
        14 => [
            'expected_products_count' => 2,
            'products' => [
                ['codigo' => 'DMB02-82', 'nombre' => 'CEPILLO DE MADERA', 'precio_divisa' => 64.13],
                ['codigo' => 'DMB82', 'nombre' => 'CEPILLO DE MADERA', 'precio_divisa' => 95.35],
            ]
        ],
        15 => [
            'expected_products_count' => 2,
            'products' => [
                ['codigo' => 'DZE02-110', 'nombre' => 'CORTADORA DE MARMOL', 'precio_divisa' => 98.88],
                ['codigo' => 'DZE110', 'nombre' => 'SIERRA CIRCULAR', 'precio_divisa' => 132.65],
            ]
        ],
    ],
    'Jadever' => [
        44 => [
            'expected_products_count' => 8,
            'products' => [
                ['codigo' => 'UJDBY1A50', 'nombre' => 'Cargador de Bateria Inversor 12/24V', 'precio_divisa' => 291.71],
                ['codigo' => 'UJDCD1B128', 'nombre' => 'Llave de Impacto Inalámbrico 20V 3/4" 1280Nm', 'precio_divisa' => 442.93],
                ['codigo' => 'UJDCD1B1285', 'nombre' => 'Llave de Impacto Inalámbrico 20V 3/4"', 'precio_divisa' => 442.93],
                ['codigo' => 'UJDCD1B12856', 'nombre' => 'Llave de Impacto Inalambrico 20V 3/4" 1280Nm', 'precio_divisa' => 0.0],
                ['codigo' => 'UJDCD1B33', 'nombre' => 'Llave de Impacto Inalambrico 20V 1/2" 330Nm', 'precio_divisa' => 122.36],
                ['codigo' => 'UJDCD1B40', 'nombre' => 'Llave de Impacto Inalámbrico 20V 1/2" 400Nm', 'precio_divisa' => 134.07],
                ['codigo' => 'UJDCD1B483', 'nombre' => 'Llave de Impacto Inalambrico 20V 1/2" 480 Nm', 'precio_divisa' => 214.07],
                ['codigo' => 'UJDCD1B53', 'nombre' => 'Llave de Impacto Inalámbrico 20V 1/2" 530Nm', 'precio_divisa' => 221.72],
            ]
        ],
        53 => [
            'expected_products_count' => 7,
            'products' => [
                ['codigo' => 'UJDRH3D38', 'nombre' => 'Rotomartillo 1600W 630rpm 10J 110V SDS Max', 'precio_divisa' => 221.36],
                ['codigo' => 'UJDRY1D131', 'nombre' => 'Rotamil Mini 130W 1/8" 8000-35000 rpm 110-120V', 'precio_divisa' => 46.23],
                ['codigo' => 'UJDWD11301', 'nombre' => 'Maquina de Soldar 130 Amp Inverter 110-120/220-240V', 'precio_divisa' => 173.43],
                ['codigo' => 'UJDWD31601', 'nombre' => 'Maquina de Soldar Inverter MMA/TIG 160 Amp 110-120/220', 'precio_divisa' => 250.92],
                ['codigo' => 'UJDWD32001', 'nombre' => 'Maquina de Soldar Inverter 200 Amp 110-120/220-240V', 'precio_divisa' => 284.12],
                ['codigo' => 'UJDWM1L15', 'nombre' => 'Maquina Polifusora 800W 20, 25, 32mm 110V', 'precio_divisa' => 48.84],
                ['codigo' => 'UJDXD151400', 'nombre' => 'Sierra Ingletadora 1400w 5000rpm 110-120V', 'precio_divisa' => 164.54],
            ]
        ],
        63 => [
            'expected_products_count' => 2,
            'products' => [
                ['codigo' => 'UJDEG1A16', 'nombre' => 'Pistola Rociadora para Pintar 450W', 'precio_divisa' => 39.54],
                ['codigo' => 'UJDEG2A50', 'nombre' => 'Pistola Rociadora para Pintar 550W', 'precio_divisa' => 54.91],
            ]
        ],
        196 => [
            'expected_products_count' => 2,
            'products' => [
                ['codigo' => 'UJDHP3A18', 'nombre' => 'Hidrojet 110V 1800W 1885psi', 'precio_divisa' => 180.78],
                ['codigo' => 'UJDHP3A22', 'nombre' => 'Hidrojet 110V 2200W 2320psi', 'precio_divisa' => 275.29],
            ]
        ],
        205 => [
            'expected_products_count' => 8,
            'products' => [
                ['codigo' => 'JDPD5550', 'nombre' => 'Candado de Hierro 50mm', 'precio_divisa' => 3.41],
                ['codigo' => 'JDPD5550L', 'nombre' => 'Candado de Hierro 50mm Arco Largo', 'precio_divisa' => 3.63],
                ['codigo' => 'JDPD5560', 'nombre' => 'Candado de Hierro 63mm', 'precio_divisa' => 5.06],
                ['codigo' => 'JDPD5560L', 'nombre' => 'Candado de Hierro 63mm Arco Largo', 'precio_divisa' => 5.25],
                ['codigo' => 'JDPD5575', 'nombre' => 'Candado de Hierro 75mm', 'precio_divisa' => 8.28],
                ['codigo' => 'JDPD5575L', 'nombre' => 'Candado de Hierro 75mm Arco Largo', 'precio_divisa' => 8.28],
                ['codigo' => 'JDPD7570', 'nombre' => 'Candado de Hierro Anticizalla 70mm', 'precio_divisa' => 7.14],
                ['codigo' => 'JDPD7580', 'nombre' => 'Candado de Hierro Anticizalla 80mm', 'precio_divisa' => 7.91],
            ]
        ],
        214 => [
            'expected_products_count' => 8,
            'products' => [
                ['codigo' => 'JDZJ2308', 'nombre' => 'Pie de Amigo 8x10"', 'precio_divisa' => 0.60],
                ['codigo' => 'JDZJ2310', 'nombre' => 'Pie de Amigo10x12"', 'precio_divisa' => 0.86],
                ['codigo' => 'JDZJ2312', 'nombre' => 'Pie de Amigo12x14"', 'precio_divisa' => 1.05],
                ['codigo' => 'UJDEHS150L', 'nombre' => 'Secador de Pelo 1800W 2 Velocidades', 'precio_divisa' => 35.75],
                ['codigo' => 'UJDFL30001', 'nombre' => 'Lampara de Mano 75W', 'precio_divisa' => 33.99],
                ['codigo' => 'UJDVR1A06', 'nombre' => 'Aspiradora 500W 110V-120V~60Hz 6LTS', 'precio_divisa' => 68.39],
                ['codigo' => 'UJDVR1A15', 'nombre' => 'Aspiradora 1100W 110V-120V~60Hz 15LTS', 'precio_divisa' => 93.27],
                ['codigo' => 'UJDVR2A20', 'nombre' => 'Aspiradora 1100W 110V-120V~60Hz 20LTS', 'precio_divisa' => 96.85],
            ]
        ],
        215 => [
            'expected_products_count' => 8,
            'products' => [
                ['codigo' => 'UJDVR2A30', 'nombre' => 'Aspiradora 1100W 110V-120V~60Hz 30LTS', 'precio_divisa' => 115.83],
                ['codigo' => 'UJDVR4A25', 'nombre' => 'Aspiradora 1100W 110V-120V~60Hz 25LTS', 'precio_divisa' => 103.49],
                ['codigo' => 'UJDVR4A35', 'nombre' => 'Aspiradora 1100W 110V-120V~60Hz 35LTS', 'precio_divisa' => 121.52],
                ['codigo' => 'UJDVR5A60', 'nombre' => 'Aspiradora 2x1200W 60L 110 V-120 V ~ 60 Hz.', 'precio_divisa' => 366.43],
                ['codigo' => 'UJDVR5A90', 'nombre' => 'Aspiradora 2x1200W 90L 110 V-120 V ~ 60 Hz.', 'precio_divisa' => 408.42],
                ['codigo' => 'UJDZZ4508T', 'nombre' => 'Licuadora 500w 1.5Lts 2 Velocidades', 'precio_divisa' => 45.78],
                ['codigo' => 'UJDZZ4509T', 'nombre' => 'Licuadora 500w 1.5Lts 10 Velocidades', 'precio_divisa' => 52.61],
                ['codigo' => 'UJDZZ4510T', 'nombre' => 'Licuadora 650w 1.5Lts 2 Velocidades', 'precio_divisa' => 58.55],
            ]
        ],
        294 => [
            'expected_products_count' => 1,
            'products' => [
                ['codigo' => 'UJDTS1A1500-SP-109', 'nombre' => 'Cubierta de la Caja del de Interruptores UJDTS1A1500', 'precio_divisa' => 0.82],
            ]
        ],
    ]
];

$driver = app(GeminiAiDriver::class);
$modelName = $driver->getModelName();
$providerName = $driver->getProviderName();
$promptVersion = $driver->getPromptVersion();
$parserVersion = config('services.ai.parser_version', 'v1');

$results = [
    'audit_metadata' => [
        'stage' => 'ETAPA_9',
        'timestamp' => date('c'),
        'execution_mode' => $isLive ? 'LIVE' : 'DRY_RUN',
        'provider' => $providerName,
        'model' => $modelName,
        'prompt_version' => $promptVersion,
        'parser_version' => $parserVersion,
        'total_insufficient_pages' => 11,
    ],
    'pages' => [],
    'metrics_summary' => [
        'dongcheng' => ['pages' => 3, 'real_products' => 0, 'ai_detected' => 0, 'correct' => 0, 'sku_correct' => 0, 'price_correct' => 0, 'false_positives' => 0, 'omitted' => 0],
        'jadever' => ['pages' => 8, 'real_products' => 0, 'ai_detected' => 0, 'correct' => 0, 'sku_correct' => 0, 'price_correct' => 0, 'false_positives' => 0, 'omitted' => 0],
        'total' => ['pages' => 11, 'real_products' => 0, 'ai_detected' => 0, 'correct' => 0, 'sku_correct' => 0, 'price_correct' => 0, 'false_positives' => 0, 'omitted' => 0],
    ],
    'idempotency_validation' => [],
    'error_simulation_results' => [],
];

// Helper to sanitize prices
function normalizePrice($val): float {
    if (is_numeric($val)) return (float) $val;
    if (is_string($val)) {
        $clean = str_replace(['$', 'Bs', ' ', ','], ['', '', '', '.'], $val);
        return (float) $clean;
    }
    return 0.0;
}

// ---------------------------------------------------------
// 1. Process 11 Pages
// ---------------------------------------------------------
echo "Processing 11 Target Pages...\n";

foreach ($targetPages as $catalogName => $pages) {
    echo "\n>>> Catalog: {$catalogName}\n";
    
    foreach ($pages as $page) {
        $chunkText = '';
        if ($catalogName === 'Dong Cheng') {
            foreach ($dcRawData['dongcheng']['page_metrics'] as $pm) {
                if ($pm['page'] === $page) {
                    $chunkText = trim($pm['full_normal_text'] . "\n" . $pm['full_red_text']);
                    break;
                }
            }
        } else {
            $cmd = "\"{$pdftotext}\" -f {$page} -l {$page} -layout \"{$jadeverPath}\" -";
            $chunkText = trim(shell_exec($cmd));
        }

        $inputChars = strlen($chunkText);
        $estInputTokens = (int) ceil(($inputChars + 1000) / 3.8); // prompt + text
        
        $gt = $groundTruth[$catalogName][$page] ?? ['expected_products_count' => 0, 'products' => []];
        $expectedCount = $gt['expected_products_count'];
        
        $pageResult = [
            'catalog' => $catalogName,
            'page_number' => $page,
            'input_characters' => $inputChars,
            'estimated_input_tokens' => $estInputTokens,
            'raw_response' => null,
            'latency_ms' => 0,
            'status' => 'pending',
            'extracted_products' => [],
            'evaluations' => [],
        ];

        if ($isLive) {
            // Live AI with retry (up to 3 attempts in case of temporary 503)
            $attempts = 0;
            $maxAttempts = 3;
            $success = false;

            while ($attempts < $maxAttempts && !$success) {
                $attempts++;
                $t0 = microtime(true);
                try {
                    $products = $driver->extractProductsFromChunk($chunkText);
                    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
                    $pageResult['latency_ms'] = $latencyMs;
                    $pageResult['status'] = 'success';
                    $pageResult['raw_response'] = json_encode($products);
                    $pageResult['extracted_products'] = $products;
                    $success = true;
                } catch (AiRateLimitException | AiTemporaryException $e) {
                    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
                    $pageResult['latency_ms'] = $latencyMs;
                    $pageResult['status'] = 'error: ' . $e->getMessage();
                    if ($attempts < $maxAttempts) {
                        sleep(2 * $attempts);
                    }
                } catch (Exception $e) {
                    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
                    $pageResult['latency_ms'] = $latencyMs;
                    $pageResult['status'] = 'error: ' . $e->getMessage();
                    break;
                }
            }
        } else {
            // Dry run: Use ground truth as simulation response to validate evaluation logic
            $pageResult['latency_ms'] = 150;
            $pageResult['status'] = 'dry_run_simulated';
            $pageResult['raw_response'] = json_encode($gt['products']);
            $pageResult['extracted_products'] = $gt['products'];
        }

        // Validate products against Ground Truth
        $detectedProducts = $pageResult['extracted_products'];
        $detectedCount = count($detectedProducts);
        
        $matchedGtIndices = [];
        $skuCorrectCount = 0;
        $priceCorrectCount = 0;
        $correctProductsCount = 0;
        $falsePositivesCount = 0;

        $productEvals = [];

        foreach ($detectedProducts as $p) {
            $code = trim($p['codigo'] ?? '');
            $name = trim($p['nombre'] ?? '');
            $priceDivisa = normalizePrice($p['precio_divisa'] ?? 0);
            
            $bestMatchIdx = null;
            $skuClassification = 'MISSING';
            $priceClassification = 'MISSING';

            // 1. First find exact or normalized code match in Ground Truth
            foreach ($gt['products'] as $idx => $expected) {
                if (in_array($idx, $matchedGtIndices, true)) continue;

                $expCode = trim($expected['codigo']);
                $expPrice = normalizePrice($expected['precio_divisa']);

                $codeMatch = ($code !== '' && (
                    strcasecmp($code, $expCode) === 0 ||
                    stripos($code, $expCode) !== false ||
                    stripos($expCode, $code) !== false
                ));

                if ($codeMatch) {
                    $bestMatchIdx = $idx;
                    $skuClassification = (strcasecmp($code, $expCode) === 0) ? 'EXACT' : 'NORMALIZED_EQUIVALENT';
                    
                    if (abs($priceDivisa - $expPrice) < 0.01) {
                        $priceClassification = 'EXACT';
                    } elseif ($priceDivisa > 0) {
                        $priceClassification = 'INCORRECT';
                    } else {
                        $priceClassification = 'MISSING';
                    }
                    break;
                }
            }

            // 2. Fallback match by price + name if code did not match
            if ($bestMatchIdx === null) {
                foreach ($gt['products'] as $idx => $expected) {
                    if (in_array($idx, $matchedGtIndices, true)) continue;
                    $expPrice = normalizePrice($expected['precio_divisa']);

                    if (abs($priceDivisa - $expPrice) < 0.01 && $priceDivisa > 0) {
                        $bestMatchIdx = $idx;
                        $skuClassification = empty($code) ? 'MISSING' : 'INCORRECT';
                        $priceClassification = 'EXACT';
                        break;
                    }
                }
            }

            if ($bestMatchIdx !== null) {
                $matchedGtIndices[] = $bestMatchIdx;
                $isSkuValid = in_array($skuClassification, ['EXACT', 'NORMALIZED_EQUIVALENT']);
                $isPriceValid = ($priceClassification === 'EXACT');

                if ($isSkuValid) $skuCorrectCount++;
                if ($isPriceValid) $priceCorrectCount++;

                if ($isSkuValid && $isPriceValid) {
                    $correctProductsCount++;
                    $classification = 'TRUE_POSITIVE';
                } else {
                    $classification = 'ATTRIBUTE_ERROR';
                }
            } else {
                $falsePositivesCount++;
                $classification = 'FALSE_POSITIVE';
            }

            $productEvals[] = [
                'codigo' => $code,
                'nombre' => $name,
                'precio_divisa' => $priceDivisa,
                'sku_classification' => $skuClassification,
                'price_classification' => $priceClassification,
                'classification' => $classification,
            ];
        }

        $omittedCount = max(0, $expectedCount - count($matchedGtIndices));

        $pageResult['evaluations'] = [
            'expected_real_products' => $expectedCount,
            'ai_detected_products' => $detectedCount,
            'correct_products' => $correctProductsCount,
            'sku_correct' => $skuCorrectCount,
            'price_correct' => $priceCorrectCount,
            'false_positives' => $falsePositivesCount,
            'omitted' => $omittedCount,
            'product_details' => $productEvals,
        ];

        $results['pages'][] = $pageResult;

        $catKey = ($catalogName === 'Dong Cheng') ? 'dongcheng' : 'jadever';
        $results['metrics_summary'][$catKey]['real_products'] += $expectedCount;
        $results['metrics_summary'][$catKey]['ai_detected'] += $detectedCount;
        $results['metrics_summary'][$catKey]['correct'] += $correctProductsCount;
        $results['metrics_summary'][$catKey]['sku_correct'] += $skuCorrectCount;
        $results['metrics_summary'][$catKey]['price_correct'] += $priceCorrectCount;
        $results['metrics_summary'][$catKey]['false_positives'] += $falsePositivesCount;
        $results['metrics_summary'][$catKey]['omitted'] += $omittedCount;

        echo "  Page {$page}: Expected {$expectedCount}, Detected {$detectedCount}, Correct {$correctProductsCount}, SKU OK {$skuCorrectCount}, Price OK {$priceCorrectCount}, Latency: {$pageResult['latency_ms']}ms\n";
    }
}

// Totals
foreach (['real_products', 'ai_detected', 'correct', 'sku_correct', 'price_correct', 'false_positives', 'omitted'] as $k) {
    $results['metrics_summary']['total'][$k] = $results['metrics_summary']['dongcheng'][$k] + $results['metrics_summary']['jadever'][$k];
}

// ---------------------------------------------------------
// 2. Idempotency Validation
// ---------------------------------------------------------
echo "\n--- Idempotency & Persistence Simulation ---\n";
$mockProducts = [
    [
        'codigo' => 'ETAPA9-TEST-001',
        'nombre' => 'Taladro Percutor 20V Etapa 9',
        'precio_divisa' => 89.99,
        'precio_bs' => 0.0,
        'descripcion' => 'Motor sin carbones brushless',
        'garantia' => '1 año',
        'condiciones' => 'Caja de 4 unidades',
        'tiempo_entrega' => 'Inmediata',
    ],
    [
        'codigo' => 'ETAPA9-TEST-002',
        'nombre' => 'Esmeril Angular 4-1/2" 20V',
        'precio_divisa' => 65.50,
        'precio_bs' => 0.0,
        'descripcion' => 'Velocidad 9000 RPM',
        'garantia' => '1 año',
        'condiciones' => '',
        'tiempo_entrega' => '',
    ]
];

DB::beginTransaction();
try {
    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'Proveedor Test Etapa 9',
        'slug' => 'proveedor-test-etapa-9',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $catalogId = DB::table('catalogs')->insertGetId([
        'supplier_id' => $supplierId,
        'filename' => 'test_etapa9.pdf',
        'original_filename' => 'test_etapa9.pdf',
        'status' => 'processing',
        'total_products' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $job = new ParseAiCatalogChunkJob("Simulated chunk", $catalogId, 1);
    
    // Pass 1: First insertion
    $refMethod = new ReflectionMethod(ParseAiCatalogChunkJob::class, 'persistProducts');
    $refMethod->setAccessible(true);
    $refMethod->invoke($job, $mockProducts, $driver);

    $countPass1 = DB::table('products')->where('catalog_id', $catalogId)->count();
    $logsPass1 = DB::table('catalog_logs')->where('catalog_id', $catalogId)->get();

    // Pass 2: Second insertion (identical)
    $refMethod->invoke($job, $mockProducts, $driver);
    $countPass2 = DB::table('products')->where('catalog_id', $catalogId)->count();
    $logsPass2 = DB::table('catalog_logs')->where('catalog_id', $catalogId)->get();

    $idempotencySuccess = ($countPass1 === count($mockProducts)) && ($countPass2 === count($mockProducts));
    
    $results['idempotency_validation'] = [
        'pass1_product_count' => $countPass1,
        'pass2_product_count' => $countPass2,
        'pass1_logs_count' => count($logsPass1),
        'pass2_logs_count' => count($logsPass2),
        'pass1_statuses' => $logsPass1->pluck('status')->toArray(),
        'pass2_statuses' => $logsPass2->pluck('status')->toArray(),
        'idempotency_confirmed' => $idempotencySuccess,
    ];
    echo "  Pass 1 Count: {$countPass1}, Pass 2 Count: {$countPass2} -> Idempotent: " . ($idempotencySuccess ? "YES" : "NO") . "\n";
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------
// 3. Error Handling Simulation
// ---------------------------------------------------------
echo "\n--- Error Handling & Exception Simulation ---\n";
$errorCases = [
    'rate_limit_429' => [
        'status' => 429,
        'body' => json_encode(['error' => ['message' => 'Rate limit exceeded']]),
        'expected_exception' => AiRateLimitException::class,
    ],
    'service_unavailable_503' => [
        'status' => 503,
        'body' => json_encode(['error' => ['message' => 'Service Unavailable']]),
        'expected_exception' => AiTemporaryException::class,
    ],
    'invalid_json_body' => [
        'status' => 200,
        'body' => '<html>Error 502 Bad Gateway</html>',
        'expected_exception' => AiResponseParseException::class,
    ],
    'invalid_candidates_json' => [
        'status' => 200,
        'body' => json_encode([
            'candidates' => [
                ['content' => ['parts' => [['text' => '{ malformed json unclosed']]]]
            ]
        ]),
        'expected_exception' => AiResponseParseException::class,
    ],
];

foreach ($errorCases as $caseName => $caseData) {
    \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake([
        '*' => Http::response($caseData['body'], $caseData['status'])
    ]);

    $mockDriver = new GeminiAiDriver('test-fake-key', 'gemini-3.6-flash');
    $caughtException = null;
    
    try {
        $mockDriver->extractProductsFromChunk("Dummy text");
    } catch (\Throwable $e) {
        $caughtException = get_class($e);
    }

    $isMatch = ($caughtException === $caseData['expected_exception']);
    $results['error_simulation_results'][$caseName] = [
        'http_status' => $caseData['status'],
        'expected_exception' => $caseData['expected_exception'],
        'actual_exception' => $caughtException,
        'passed' => $isMatch,
    ];
    echo "  Case {$caseName}: Caught {$caughtException} (Expected: {$caseData['expected_exception']}) -> " . ($isMatch ? "PASS" : "FAIL") . "\n";
}

// ---------------------------------------------------------
// 4. Save Structured Dataset
// ---------------------------------------------------------
$storageDir = storage_path('app/private/audits');
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}
$jsonPath = $storageDir . '/etapa9_ai_validation.json';
file_put_contents($jsonPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nSaved audit dataset to: {$jsonPath}\n";

// ---------------------------------------------------------
// 5. Print Final Summary Table
// ---------------------------------------------------------
echo "\n=================================================================\n";
echo " RESUMEN DE MÉTRICAS ETAPA 9\n";
echo "=================================================================\n";
printf("%-15s | %10s | %10s | %10s | %10s | %10s | %10s | %10s\n", 
    "Catálogo", "Páginas", "Reales", "Detectados", "Correctos", "SKU OK", "Precio OK", "Omitidos");
echo str_repeat("-", 95) . "\n";

foreach (['dongcheng' => 'Dong Cheng', 'jadever' => 'Jadever', 'total' => 'TOTAL'] as $k => $label) {
    $m = $results['metrics_summary'][$k];
    printf("%-15s | %10d | %10d | %10d | %10d | %10d | %10d | %10d\n",
        $label, $m['pages'], $m['real_products'], $m['ai_detected'], $m['correct'], $m['sku_correct'], $m['price_correct'], $m['omitted']);
}

$tot = $results['metrics_summary']['total'];
$coverage = $tot['real_products'] > 0 ? round(($tot['ai_detected'] / $tot['real_products']) * 100, 2) : 0;
$precision = $tot['ai_detected'] > 0 ? round(($tot['correct'] / $tot['ai_detected']) * 100, 2) : 0;
$skuAccuracy = $tot['ai_detected'] > 0 ? round(($tot['sku_correct'] / $tot['ai_detected']) * 100, 2) : 0;
$priceAccuracy = $tot['ai_detected'] > 0 ? round(($tot['price_correct'] / $tot['ai_detected']) * 100, 2) : 0;

echo "\nMÉTRICAS GLOBALES:\n";
echo "  - Cobertura de productos: {$coverage}%\n";
echo "  - Precisión de productos: {$precision}%\n";
echo "  - Exactitud de SKU:       {$skuAccuracy}%\n";
echo "  - Exactitud de Precios:   {$priceAccuracy}%\n";
echo "=================================================================\n";
