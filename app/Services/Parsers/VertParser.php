<?php

namespace App\Services\Parsers;

class VertParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // Formato VERT devuelto por Smalot PdfParser:
        // * (o ., -, /) [Descripción multi-línea]
        // [Cantidad]PZA (o UND)
        // [Precio]/PZA
        // Min:[Minimo]PZA/
        // [SKU] 0 (o tab 0)
        
        // Usamos una aproximación dividiendo el texto para ser más seguros o regex si es consistente.
        preg_match_all('/^[\*\.\/\-]\s*(.*?)\n\d+[A-Za-z]+\n([\d,]+)\/[A-Za-z]+\nMin:\d+[A-Za-z]*\/?\n(.*?)\s+0$/sm', $normalText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $desc = preg_replace('/\s+/', ' ', trim($match[1]));
            $precio = $this->parseDecimal($match[2]);
            $sku = trim($match[3]);

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
        $value = str_replace(',', '.', $value); // VERT usa coma para decimales (e.g. 0,78)
        return (float) $value;
    }
}
