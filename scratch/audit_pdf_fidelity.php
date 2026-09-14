<?php

require 'c:/Proyectos/Personales/ferrevital-catalog-bot/vendor/autoload.php';

$popplerBin = 'C:\\Tools\\poppler\\Library\\bin';
$pdftotext = $popplerBin . '\\pdftotext.exe';
$jadeverPath = 'c:/Proyectos/Personales/ferrevital-catalog-bot/public/PDFs/Catalogo Jadever 04-05-2026.pdf';
$jsonPath = 'c:/Proyectos/Personales/ferrevital-catalog-bot/storage/app/private/audits/etapa9_ai_validation.json';
$auditData = json_decode(file_get_contents($jsonPath), true);

$jadeverAiPages = [44, 53, 63, 196, 205, 214, 215, 294];

echo "=================================================================\n";
echo " AUDITORÍA EXACTA DE FIDELIDAD CONTRA EL TEXTO DEL PDF\n";
echo "=================================================================\n\n";

$realGroundTruth = [];

foreach ($jadeverAiPages as $page) {
    $cmd = "\"{$pdftotext}\" -f {$page} -l {$page} -layout \"{$jadeverPath}\" -";
    $text = shell_exec($cmd);
    
    // Find all SKUs (UJD... or JD...) and Prices ($ xx.xx) in text
    preg_match_all('/\b(UJD[A-Z0-9\-]+|JD[A-Z0-9\-]+)\b/i', $text, $skuMatches);
    preg_match_all('/\$\s*([\d\.,]+)/', $text, $priceMatches);
    
    echo ">>> JADEVER PÁGINA {$page}:\n";
    echo "  SKUs encontrados en PDF: " . implode(', ', array_unique($skuMatches[1])) . "\n";
    echo "  Precios encontrados en PDF: " . implode(', ', $priceMatches[1]) . "\n\n";
}
