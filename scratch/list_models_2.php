<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('services.gemini.api_key');
$models = json_decode(file_get_contents('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey), true);

foreach($models['models'] as $m) {
    echo $m['name'] . "\n";
}
