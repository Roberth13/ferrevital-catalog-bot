<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('services.gemini.api_key');
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$response = file_get_contents($url);
echo $response;
