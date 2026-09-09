<?php
require __DIR__ . '/../vendor/autoload.php';

$text = file_get_contents(__DIR__ . '/../storage/app/vert_smalot.txt');
$factory = new \App\Services\Parsers\CatalogParserFactory(new \App\Services\ProductParser());

// Simulamos temporalmente que Factory tiene Vert
$parser = new \App\Services\Parsers\VertParser();
$products = $parser->parse($text);

echo "Total: " . count($products) . "\n";
foreach (array_slice($products, 0, 5) as $p) {
    echo "SKU: " . $p['codigo'] . "\n";
    echo "PRECIO: " . $p['precio_divisa'] . "\n";
    echo "DESC: " . $p['nombre'] . "\n";
    echo "-------\n";
}
