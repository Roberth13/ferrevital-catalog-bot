<?php

require 'c:/Proyectos/Personales/ferrevital-catalog-bot/vendor/autoload.php';

$popplerBin = 'C:\\Tools\\poppler\\Library\\bin';
$pdftotext = $popplerBin . '\\pdftotext.exe';
$jadeverPath = 'c:/Proyectos/Personales/ferrevital-catalog-bot/public/PDFs/Catalogo Jadever 04-05-2026.pdf';

$jadeverAiPages = [44, 53, 63, 196, 205, 214, 215, 294];

foreach ($jadeverAiPages as $page) {
    $cmd = "\"{$pdftotext}\" -f {$page} -l {$page} -layout \"{$jadeverPath}\" -";
    $output = shell_exec($cmd);
    echo "==================== JADEVER PAGE {$page} ====================\n";
    echo $output . "\n";
}
