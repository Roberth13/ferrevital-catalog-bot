<?php
require __DIR__ . '/../vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile(__DIR__ . '/../public/PDFs/WADFOW CATALOGO  10-8-26.pdf');
$text = $pdf->getPages()[10]->getText();

$factory = new \App\Services\Parsers\CatalogParserFactory(new \App\Services\ProductParser());
$parser = $factory->make($text);

$products = $parser->parse($text);

foreach (array_slice($products, 0, 5) as $p) {
    echo "SKU: " . $p['codigo'] . "\n";
    echo "PRECIO: " . $p['precio_divisa'] . "\n";
    echo "DESC: " . $p['nombre'] . "\n";
    echo "-------\n";
}
