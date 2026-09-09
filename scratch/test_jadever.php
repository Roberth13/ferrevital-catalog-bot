<?php
$text = file_get_contents(__DIR__ . '/../storage/app/jadever_sample.txt');

// Regex to find all SKUs. Jadever SKUs in this sample start with CT (like CTBL3009W02) or similar. Let's look for uppercase alphanumeric 10+ chars.
preg_match_all('/([A-Z0-9]{8,})/', $text, $skuMatches);

// Regex to find prices
preg_match_all('/\$ (\d+\.\d{2})/', $text, $priceMatches);

print_r($skuMatches[1]);
print_r($priceMatches[1]);

// Descriptions are trickier. 
