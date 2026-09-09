<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PdfTextExtractor;
use App\Services\CatalogOcrProcessor;
use App\Services\Parsers\CatalogParserFactory;
use Illuminate\Support\Facades\Log;

// Desactivar logs pesados si los hay
Log::setDefaultDriver('stderr');

$pdfDir = __DIR__ . '/../public/PDFs';
$files = glob($pdfDir . '/*.pdf');

$pdfTextExtractor = app(PdfTextExtractor::class);
$ocrProcessor = app(CatalogOcrProcessor::class);

echo "Iniciando prueba de parsers en todos los PDFs...\n";
echo "Campos a evaluar: CÓDIGO, NOMBRE, PRECIO BS, PRECIO DIVISA, DESCRIPCIÓN, GARANTÍA, CONDICIONES, TIEMPO DE ENTREGA\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    echo "=========================================================\n";
    echo "Procesando: {$filename}\n";
    echo "=========================================================\n";

    $products = [];
    $text = '';
    
    try {
        $text = $pdfTextExtractor->extract($file);

        if (strlen(trim($text)) < 500) {
            echo "-> PDF sin texto seleccionable (OCR necesario). Procesando solo la página 2 y 3 para prueba rápida...\n";
            $pagesData = [];
            // Solo 2 páginas para no colapsar la consola y el tiempo
            for ($page = 2; $page <= 3; $page++) {
                $pagesData[] = $ocrProcessor->processPage($file, $page, 150);
            }
            
            foreach ($pagesData as $pageData) {
                if (empty($pageData['normal_text'])) continue;
                $parser = CatalogParserFactory::make($pageData['normal_text']);
                $pageProducts = $parser->parse($pageData['normal_text'], $pageData['red_text'] ?? '');
                $products = array_merge($products, $pageProducts);
            }
        } else {
            echo "-> Texto extraíble encontrado (" . strlen($text) . " caracteres).\n";
            $parser = CatalogParserFactory::make($text);
            echo "-> Parser seleccionado: " . get_class($parser) . "\n";
            $products = $parser->parse($text);
        }

        echo "-> Total productos extraídos: " . count($products) . "\n\n";

        if (count($products) > 0) {
            echo "Muestra de los primeros 2 productos:\n";
            foreach (array_slice($products, 0, 2) as $index => $p) {
                echo " Producto #" . ($index + 1) . ":\n";
                echo "  - CÓDIGO         : " . ($p['codigo'] ?? '❌ VACÍO') . "\n";
                echo "  - NOMBRE         : " . ($p['nombre'] ?? '❌ VACÍO') . "\n";
                echo "  - PRECIO BS      : " . ($p['precio_bs'] ?? '❌ VACÍO') . "\n";
                echo "  - PRECIO DIVISA  : " . ($p['precio_divisa'] ?? '❌ VACÍO') . "\n";
                echo "  - DESCRIPCIÓN    : " . ($p['descripcion'] ?? '❌ VACÍO') . "\n";
                echo "  - GARANTÍA       : " . ($p['garantia'] ?? '❌ VACÍO') . "\n";
                echo "  - CONDICIONES    : " . ($p['condiciones'] ?? '❌ VACÍO') . "\n";
                echo "  - TIEMPO ENTREGA : " . ($p['tiempo_entrega'] ?? '❌ VACÍO') . "\n";
                echo "\n";
            }
            
            // Evaluar campos faltantes en toda la muestra
            $missingFields = [
                'garantia' => 0, 'condiciones' => 0, 'tiempo_entrega' => 0, 'descripcion' => 0
            ];
            foreach ($products as $p) {
                if (empty($p['garantia'])) $missingFields['garantia']++;
                if (empty($p['condiciones'])) $missingFields['condiciones']++;
                if (empty($p['tiempo_entrega'])) $missingFields['tiempo_entrega']++;
                if (empty($p['descripcion'])) $missingFields['descripcion']++;
            }
            
            echo "Análisis de vacíos en " . count($products) . " productos:\n";
            foreach ($missingFields as $field => $count) {
                $percentage = round(($count / count($products)) * 100, 2);
                echo "  - $field vacío en $percentage% de los casos.\n";
            }
            
        } else {
            echo "❌ No se extrajo ningún producto.\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Error procesando $filename: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}
