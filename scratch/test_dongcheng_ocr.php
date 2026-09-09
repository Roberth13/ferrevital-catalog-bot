<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$processor = app(\App\Services\CatalogOcrProcessor::class);

echo "Processing Dong Cheng page 4...\n";
$result = $processor->processPage(__DIR__ . '/../public/PDFs/Dong Cheng.pdf', 4, 300);

file_put_contents(__DIR__ . '/../storage/app/dongcheng_ocr_sample.txt', $result['normal_text']);
echo "Done!\n";
