<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = \App\Models\Catalog::find(2);
app(\App\Services\CatalogProcessor::class)->process($c);
echo "Terminado\n";
