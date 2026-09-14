<?php

require 'c:/Proyectos/Personales/ferrevital-catalog-bot/vendor/autoload.php';
$app = require_once 'c:/Proyectos/Personales/ferrevital-catalog-bot/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PdfTextExtractor;

$popplerBin = config('services.poppler.bin_path', 'C:\\Tools\\poppler\\Library\\bin');
$pdftotext = $popplerBin . '\\pdftotext.exe';

$dcJsonPath = 'C:/Users/rober/.gemini/antigravity-ide/brain/8cff0ba5-c805-492b-b043-57fe0fc49612/scratch/e2e_report_raw.json';
$dcRawData = json_decode(file_get_contents($dcJsonPath), true);
$jadeverPath = base_path('public/PDFs/Catalogo Jadever 04-05-2026.pdf');

$dcAiPages = [8, 14, 15];
$jadeverAiPages = [44, 53, 63, 196, 205, 214, 215, 294];

echo "=== DONG CHENG TEXTS ===\n";
foreach ($dcRawData['dongcheng']['page_metrics'] as $pm) {
    if (in_array($pm['page'], $dcAiPages)) {
        echo "--- PAGE {$pm['page']} ---\n";
        echo "[NORMAL TEXT]\n" . $pm['full_normal_text'] . "\n";
        echo "[RED TEXT]\n" . $pm['full_red_text'] . "\n\n";
    }
}

echo "=== JADEVER TEXTS ===\n";
foreach ($jadeverAiPages as $page) {
    $cmd = "\"{$pdftotext}\" -f {$page} -l {$page} -layout \"{$jadeverPath}\" -";
    $output = shell_exec($cmd);
    echo "--- PAGE {$page} ---\n";
    echo $output . "\n\n";
}
