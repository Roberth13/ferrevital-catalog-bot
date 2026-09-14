<?php

require 'c:/Proyectos/Personales/ferrevital-catalog-bot/vendor/autoload.php';

$jsonPath = 'c:/Proyectos/Personales/ferrevital-catalog-bot/storage/app/private/audits/etapa9_ai_validation.json';
$data = json_decode(file_get_contents($jsonPath), true);

echo "=================================================================\n";
echo " DETALLE DE EXTRACCIÓN REAL DE IA POR PÁGINA\n";
echo "=================================================================\n\n";

foreach ($data['pages'] as $page) {
    echo "=================================================================\n";
    echo " Catálogo: {$page['catalog']} | Página: {$page['page_number']}\n";
    echo " Status: {$page['status']} | Latency: {$page['latency_ms']} ms\n";
    echo " Input chars: {$page['input_characters']} | Est input tokens: {$page['estimated_input_tokens']}\n";
    echo "=================================================================\n";
    
    if (empty($page['extracted_products'])) {
        echo " [!] No products extracted (or error occurred).\n\n";
        continue;
    }
    
    echo " Productos extraídos por Gemini (" . count($page['extracted_products']) . "):\n";
    foreach ($page['extracted_products'] as $idx => $p) {
        $num = $idx + 1;
        echo "  #{$num} | SKU: [{$p['codigo']}] | Precio $: [{$p['precio_divisa']}] | Precio Bs: [{$p['precio_bs']}]\n";
        echo "       Nombre: {$p['nombre']}\n";
        if (!empty($p['descripcion'])) {
            echo "       Desc: " . mb_substr($p['descripcion'], 0, 100) . "...\n";
        }
        if (!empty($p['garantia'])) {
            echo "       Garantía: {$p['garantia']}\n";
        }
    }
    echo "\n";
}
