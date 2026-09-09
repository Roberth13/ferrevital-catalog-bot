<?php

namespace App\Services\Parsers;

class IngcoParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // El formato de INGCO (raw) parece tener los productos de esta forma:
        // " TALADRO DE IMPACTO 1/2 BL INALAMBRICA
        // 42V 69N.M (MALETA) MARCA INGCO
        // Ref. 162.34
        // UCIDLI426982

        // Usaremos esta regex para extraer la descripción (lo que está entre " y Ref), el precio (después de Ref.) y el código (la siguiente línea alfanumérica larga).
        preg_match_all('/"\s*(.*?)\s*Ref\.\s*([\d.,]+)\s*([A-Z0-9]{4,})/s', $normalText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $sku = strtoupper($match[3]);
            $precio = $this->parseDecimal($match[2]);
            $desc = preg_replace('/\s+/', ' ', trim($match[1]));

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
        $value = str_replace(',', '', $value); // INGCO usually uses . for decimals in this catalog, but if they use thousands we strip comma
        return (float) $value;
    }
}
