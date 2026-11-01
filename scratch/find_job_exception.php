<?php
$logPath = __DIR__ . '/../storage/logs/laravel.log';
$content = file_get_contents($logPath);
$chunks = explode('[2026', $content); // Split by timestamp
$exceptions = [];

foreach($chunks as $chunk) {
    if (strpos($chunk, 'ParseAiCatalogChunkJob') !== false && strpos($chunk, 'exception') !== false) {
        $exceptions[] = '[2026' . $chunk;
    }
}

if (!empty($exceptions)) {
    // get the last 2 exceptions
    $lastExceptions = array_slice($exceptions, -2);
    foreach($lastExceptions as $e) {
        echo "===================\n";
        echo substr($e, 0, 1500) . "\n";
    }
} else {
    echo "No exceptions found for ParseAiCatalogChunkJob";
}
