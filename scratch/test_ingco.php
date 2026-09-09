<?php
$text = file_get_contents(__DIR__ . '/../storage/app/ingco_sample_raw.txt');

preg_match_all('/"\s*(.*?)\s*Ref\.\s*([\d.,]+)\s*([A-Z0-9]{4,})/s', $text, $matches, PREG_SET_ORDER);

foreach ($matches as $match) {
    echo "SKU: " . $match[3] . "\n";
    echo "PRICE: " . $match[2] . "\n";
    echo "DESC: " . preg_replace('/\s+/', ' ', $match[1]) . "\n";
    echo "-------\n";
}
