<?php
$logPath = __DIR__ . '/../storage/logs/laravel.log';
$lines = file($logPath);
$chunk = [];
$found = false;
foreach(array_reverse($lines) as $line) {
    array_unshift($chunk, $line);
    if (strpos($line, 'ParseAiCatalogChunkJob') !== false && strpos($line, 'Exception') !== false) {
        $found = true;
        break;
    }
    if (count($chunk) > 100) array_pop($chunk); // limit context
}

if ($found) {
    echo "FOUND EXCEPTION:\n";
    echo implode("", $chunk);
} else {
    echo "Not found in last lines.";
}
