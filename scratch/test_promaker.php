<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Parsers\CatalogParserFactory;
use App\Services\ProductParser; // To feed to factory in standalone script for testing

// Create a mock of ProductParser or just instantiate it since it has no dependencies
$factory = new CatalogParserFactory(new ProductParser());

$text = file_get_contents(__DIR__ . '/../storage/app/promaker_sample.txt');

// Mock Laravel's report() function if not in full app context, but since we require autoload, it might work, or we can just test the parser directly.
$parser = $factory->make($text);

echo "Instantiated Parser: " . get_class($parser) . "\n\n";

$products = $parser->parse($text);

foreach ($products as $p) {
    echo "SKU: " . $p['codigo'] . "\n";
    echo "NAME: " . $p['nombre'] . "\n";
    echo "PRICE: " . $p['precio_divisa'] . "\n";
    echo "-------\n";
}
