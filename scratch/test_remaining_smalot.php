<?php
require __DIR__ . '/../vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();

try {
    $pdf = $parser->parseFile(__DIR__ . '/../public/PDFs/3. CATALOGO RONIX CCS AGOSTO $.pdf');
    file_put_contents(__DIR__ . '/../storage/app/ronix_smalot.txt', $pdf->getText());
    echo "RONIX done\n";
} catch (Exception $e) {
    echo "RONIX error: " . $e->getMessage() . "\n";
}

try {
    $pdf = $parser->parseFile(__DIR__ . '/../public/PDFs/CATALOGO VERT AGOSTO 10-08_compressed.pdf');
    file_put_contents(__DIR__ . '/../storage/app/vert_smalot.txt', $pdf->getText());
    echo "VERT done\n";
} catch (Exception $e) {
    echo "VERT error: " . $e->getMessage() . "\n";
}

try {
    $pdf = $parser->parseFile(__DIR__ . '/../public/PDFs/LISTA 03 DYLLU 22-08-2026.pdf');
    file_put_contents(__DIR__ . '/../storage/app/dyllu_smalot.txt', $pdf->getText());
    echo "DYLLU done\n";
} catch (Exception $e) {
    echo "DYLLU error: " . $e->getMessage() . "\n";
}
