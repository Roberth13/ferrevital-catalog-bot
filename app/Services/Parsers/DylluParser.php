<?php

namespace App\Services\Parsers;

class DylluParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        $blocks = explode("CODIGO ", $normalText);
        
        for ($i = 1; $i < count($blocks); $i++) {
            $block = $blocks[$i];
            
            // SKU: First word of the block
            if (!preg_match('/^(\d+)/', ltrim($block), $skuMatch)) {
                continue;
            }
            $sku = $skuMatch[1];
            
            // Extract Price
            if (!preg_match('/PRECIO\s+([\d.,]+)/i', $block, $priceMatch)) {
                continue;
            }
            $precio = $this->parseDecimal($priceMatch[1]);
            
            // Extract Description (everything between SKU and the first metadata field)
            // Metadata fields: PRECIO, STOCK, EMP.MAYOR, Polí
            preg_match('/^\d+\s*(.*?)(PRECIO|STOCK|EMP\.MAYOR|Pol)/is', $block, $descMatch);
            $desc = '';
            if (isset($descMatch[1])) {
                $desc = preg_replace('/\s+/', ' ', trim($descMatch[1]));
            }
            
            $products[] = [
                'codigo' => $sku,
                'nombre' => $desc,
                'precio_bs' => null,
                'precio_divisa' => $precio,
                'descripcion' => $desc,
                'garantia' => null,
                'condiciones' => null,
                'tiempo_entrega' => null,
                'raw_text' => "CODIGO " . $block,
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
