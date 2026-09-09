<?php

namespace App\Services\Parsers;

class WadfowParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // El formato de Wadfow devuelto por Smalot PdfParser es:
        // [Precio]REF:
        // [SKU]
        // *      [Descripción]
        
        // Ejemplo:
        // 3.63REF:  
        // WKK1K31
        // *      SET DE 3 CUCHILLO DE COCINA (8/5/3.5)MARCA WADFOW

        preg_match_all('/([\d.,]+)REF:\s*\n([A-Z0-9]{5,15})\n\*\s+(.*?)(?=\n|$)/s', $normalText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $precio = $this->parseDecimal($match[1]);
            $sku = trim($match[2]);
            $desc = trim($match[3]);

            $products[] = [
                'codigo' => $sku,
                'nombre' => $desc,
                'precio_bs' => null,
                'precio_divisa' => $precio,
                'descripcion' => $desc,
                'garantia' => null,
                'condiciones' => null,
                'tiempo_entrega' => null,
                'raw_text' => $match[0],
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace(',', '', $value);
        return (float) $value;
    }
}
