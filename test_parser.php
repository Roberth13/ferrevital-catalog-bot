<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app('App\Services\Parsers\DongChengParser');
$normal = file_get_contents('storage/app/dongcheng_ocr_sample.txt');
$red = file_get_contents('storage/app/dongcheng_ocr_sample_red.txt');
echo json_encode($parser->parse($normal, $red), JSON_PRETTY_PRINT);
