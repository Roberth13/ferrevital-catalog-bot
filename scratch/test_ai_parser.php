<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Parsers\AiCatalogParser;
use Illuminate\Support\Facades\Log;

Log::setDefaultDriver('stderr');

$aiParser = app(AiCatalogParser::class);

$testText = <<<TEXT
JADEVER CORP, C.A is appointed as the authorized distributor for JADEVER  tools in  Venezuela. Valid period: From Jan.1 st 2026 to Dec.31 st 2026. Details for JADEVER CORP, C.A: Add: CALLE 18 CASA NRO 16-95 BARRIO CORAZON DE JESUS SIERRA MAESTRA (MARACAIBO) (CAPITAL) ZULIA ZONA POSTAL 4004 Jan.1st 2026 TOGROUP TECHNOLOGY (SUZHOU) CO.,LTD AUTHORIZED DISTRIBUTOR Bombillo LED PAR30 E27 9W 6500K 110-240V Luminika
CODIGO: CTBL3009W02
PRECIO: 7.84$
1 Año de Garantía.
Venta minima 12 unidades.

Bombillo LED A60 E27 9W 6500K 110V Luminika
CODIGO: CTBL6009W02
PRECIO: 0.99$
1 Año de Garantía.
TEXT;

echo "Probando AiCatalogParser con texto de prueba...\n\n";

try {
    $products = $aiParser->parse($testText);
    
    echo "Productos extraídos: " . count($products) . "\n\n";
    
    foreach ($products as $i => $p) {
        echo "Producto #" . ($i + 1) . ":\n";
        print_r($p);
        echo "---------------------------\n";
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
