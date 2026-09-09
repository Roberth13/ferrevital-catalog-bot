<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$processor = app(\App\Services\CatalogProcessor::class);

// Creamos un dummy catalog temporal en DB
$catalog = \App\Models\Catalog::create([
    'filename' => 'public/PDFs/Dong Cheng.pdf', // Path local real (dentro de storage, pero lo mapearemos)
    'original_name' => 'Dong Cheng.pdf',
    'status' => 'pending',
    'total_products' => 0,
]);

// Como el CatalogProcessor lee de Storage::disk('local')->path($catalog->filename), 
// vamos a copiar el PDF real al storage para la prueba
if (!file_exists(storage_path('app/public/PDFs'))) {
    mkdir(storage_path('app/public/PDFs'), 0777, true);
}
copy(__DIR__ . '/../public/PDFs/Dong Cheng.pdf', storage_path('app/public/PDFs/Dong Cheng.pdf'));

echo "Procesando Catalogo...\n";
$processed = $processor->process($catalog);

echo "Estado: " . $processed->status . "\n";
echo "Total Productos: " . $processed->total_products . "\n";

foreach ($processed->products()->take(5)->get() as $p) {
    echo "SKU: " . $p->codigo . "\n";
    echo "PRECIO: " . $p->precio_divisa . "\n";
    echo "DESC: " . $p->nombre . "\n";
    echo "-------\n";
}
