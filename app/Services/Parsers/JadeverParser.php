<?php

namespace App\Services\Parsers;

class JadeverParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // El formato de Jadever devuelto por Smalot PdfParser es secuencial perfecto:
        // [Nombre]
        // [SKU]
        // [N/A u otra linea]
        // Unit Uni/Min Uni/Pack Precio
        // [Unidad] [Min] [Pack] $ [Precio]

        $pattern = '/(.*?)\n([A-Z0-9]{8,15})\n(.*?)\nUnit Uni\/Min Uni\/Pack Precio\n[A-Z]+\s+\d+\s+[\d.]+\s+\$\s+([\d.]+)/s';

        // Dividir el texto en bloques usando "Unit Uni/Min Uni/Pack Precio" como ancla puede ser más seguro si el patrón falla, pero intentemos preg_match_all primero.
        // Haremos un explode por "Unit Uni/Min Uni/Pack Precio" para procesar cada bloque de producto de forma segura hacia atrás y hacia adelante.

        $blocks = explode("Unit Uni/Min Uni/Pack Precio", $normalText);
        
        // $blocks[0] es la cabecera antes del primer producto
        for ($i = 1; $i < count($blocks); $i++) {
            $prevBlock = $blocks[$i - 1]; // Contiene el nombre y SKU al final
            $nextBlock = $blocks[$i];     // Contiene los precios al principio

            // Extraer Precio del inicio de nextBlock
            // Ej: "\nPZA 1 1.00 $ 7.84\nSiguiente Producto..."
            if (preg_match('/^\s*[A-Z]+\s+\d+\s+[\d.]+\s+\$\s+([\d.,]+)/', $nextBlock, $priceMatch)) {
                $precio = $this->parseDecimal($priceMatch[1]);
            } else {
                continue; // No pudimos sacar precio
            }

            // Extraer SKU y Nombre del final de prevBlock
            $prevLines = explode("\n", trim($prevBlock));
            $prevLines = array_values(array_filter(array_map('trim', $prevLines), fn($l) => $l !== ''));
            
            if (count($prevLines) < 2) {
                continue;
            }

            // Las ultimas lineas suelen ser:
            // [Nombre L1]
            // [Nombre L2 (Opcional)]
            // [SKU]
            // N/A (o Nombre repetido)
            
            $lastLine = $prevLines[count($prevLines) - 1];
            $secondToLastLine = $prevLines[count($prevLines) - 2];
            $thirdToLastLine = $prevLines[count($prevLines) - 3] ?? '';

            // El SKU suele ser alfanumérico y estar en secondToLastLine si lastLine es "N/A" o una descripción repetida
            // Jadever/Oveja SKUs: CTBL3009W02, OV9500055, etc. Todos empiezan por letras y tienen > 8 chars
            
            $sku = '';
            $descLines = [];

            if ($this->looksLikeSku($secondToLastLine)) {
                $sku = $secondToLastLine;
                $descLines = array_slice($prevLines, 0, count($prevLines) - 2);
            } elseif ($this->looksLikeSku($thirdToLastLine)) {
                $sku = $thirdToLastLine;
                $descLines = array_slice($prevLines, 0, count($prevLines) - 3);
            } elseif ($this->looksLikeSku($lastLine)) {
                $sku = $lastLine;
                $descLines = array_slice($prevLines, 0, count($prevLines) - 1);
            } else {
                continue;
            }

            // Limpiar descripción de basura
            $descLines = array_filter($descLines, function($line) {
                if (stripos($line, 'www.') !== false) return false;
                if (preg_match('/^\d+$/', $line)) return false; // Nro de pagina
                // Si la linea es la info del producto anterior ej: "PZA 1 1.00 $ 7.84"
                if (preg_match('/^[A-Z]+\s+\d+\s+[\d.]+\s+\$\s+[\d.,]+/', $line)) return false;
                return true;
            });

            $desc = implode(" ", $descLines);

            $products[] = [
                'codigo' => $sku,
                'nombre' => $desc,
                'precio_bs' => null,
                'precio_divisa' => $precio,
                'descripcion' => $desc,
                'garantia' => null,
                'condiciones' => null,
                'tiempo_entrega' => null,
                'raw_text' => $prevBlock . " Unit Uni/Min Uni/Pack Precio " . $nextBlock,
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function looksLikeSku(string $text): bool
    {
        return preg_match('/^[A-Z0-9]{8,20}$/', $text) === 1;
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace(',', '', $value);
        return (float) $value;
    }
}
