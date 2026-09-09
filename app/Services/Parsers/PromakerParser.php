<?php

namespace App\Services\Parsers;

class PromakerParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // Remove weird characters, normalize newlines
        $normalText = str_replace(["\r\n", "\r"], "\n", $normalText);
        $lines = explode("\n", $normalText);

        $blocks = [];
        $currentBlock = [];

        // 1. Chunk into blocks based on SKU pattern PRO-
        foreach ($lines as $line) {
            // Check if line contains a Promaker SKU (e.g. PRO-TR300, PRO-TP550KIT)
            if (preg_match('/\bPRO-[A-Z0-9\-]+\b/i', $line)) {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = [$line];
            } elseif (!empty($currentBlock)) {
                $currentBlock[] = $line;
            }
        }
        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        // 2. Parse each block
        foreach ($blocks as $blockLines) {
            $blockText = implode(" ", array_map('trim', $blockLines));
            $blockText = preg_replace('/\s+/', ' ', $blockText);

            // Extract SKU
            if (preg_match('/\b(PRO-[A-Z0-9\-]+)\b/i', $blockText, $match)) {
                $sku = strtoupper($match[1]);
            } else {
                continue;
            }

            // Extract Prices and Existencia
            // Usually ends with: [Existencia] [Precio] [Precio 40%]
            // Example: "45 67,43 40,46" or "- 74,11 44,47"
            $precio = null;
            $existencia = null;
            
            // Match the end of the string looking for the two prices (with comma) and an optional dash/number before it
            if (preg_match('/(?:(\d+|-)\s+)?(\d+,\d{2})\s+(\d+,\d{2})/', $blockText, $matches)) {
                $existenciaRaw = $matches[1] ?? '';
                $precioRaw = $matches[2]; // The main price (middle one or the one before the 40%)
                
                $precio = $this->parseDecimal($precioRaw);
                $existencia = ($existenciaRaw === '-') ? 0 : (int)$existenciaRaw;
                
                // Remove the matched pricing/stock from the block text to leave only the description
                $blockText = str_replace($matches[0], '', $blockText);
            }

            // Extract Description
            // Remove the SKU and any leading numbers
            $desc = trim(str_replace($sku, '', $blockText));
            $desc = preg_replace('/^\d+\s+/', '', $desc); // Remove the item number like "1" or "3"
            $desc = trim($desc, " -");

            $products[] = [
                'codigo' => $sku,
                'nombre' => $desc, // Use description as name
                'precio_bs' => null,
                'precio_divisa' => $precio,
                'descripcion' => $desc,
                'garantia' => null,
                'condiciones' => null,
                'tiempo_entrega' => null,
                'raw_text' => implode("\n", $blockLines),
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace('.', '', $value); // Remove thousands separator
        $value = str_replace(',', '.', $value); // Replace decimal comma with dot
        return (float) $value;
    }
}
