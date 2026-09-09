<?php
require __DIR__ . '/../vendor/autoload.php';

$text = file_get_contents(__DIR__ . '/../storage/app/ronix_sample.txt');
$factory = new \App\Services\Parsers\CatalogParserFactory(new \App\Services\ProductParser());

$parser = new \App\Services\Parsers\RonixParser();
$products = $parser->parse($text);

echo "Total: " . count($products) . "\n";
foreach ($products as $p) {
    echo "SKU: " . $p['codigo'] . "\n";
    echo "PRECIO: " . $p['precio_divisa'] . "\n";
    echo "DESC: " . $p['nombre'] . "\n";
    echo "-------\n";
}
