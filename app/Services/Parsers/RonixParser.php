<?php

namespace App\Services\Parsers;

class RonixParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $products = [];
        
        // El formato de Ronix extraído por pdftotext es:
        // [Nombre del producto]
        // [SKU] (ej. 3111V)
        // Voltaje ... (varias specs)
        // Precio: $ [Precio]

        // Usaremos regex para buscar el bloque desde el código hasta el Precio: $
        // O más fácil: buscar "Precio: $ [\d.]+" y hacia atrás buscar el código.
        // O: (.*?)\n([0-9]{3,5}[A-Z]{0,2})\n(?:.*?)Precio: \$\s+([\d.,]+)
        
        preg_match_all('/([^\n]+)\n([0-9]{3,5}[A-Z]{0,2})\n.*?Precio:\s*\$\s*([\d.,]+)/s', $normalText, $matches, PREG_SET_ORDER);

        // Sin embargo, .*? puede comerse otros productos si no somos cuidadosos.
        // Mejor separar por "Precio: $"
        $blocks = explode('Precio: $', $normalText);
        
        for ($i = 0; $i < count($blocks) - 1; $i++) {
            $block = $blocks[$i];
            
            // El precio está al inicio del *siguiente* bloque
            $nextBlock = ltrim($blocks[$i + 1]);
            if (!preg_match('/^([\d.,]+)/', $nextBlock, $priceMatch)) {
                continue;
            }
            $precio = $this->parseDecimal($priceMatch[1]);
            
            // En el bloque actual, buscamos el SKU. Suele ser una línea con números y a veces letras, ej: 3111V, 2816LV, 8910-40V
            // Vamos a buscar la última línea que parezca un código antes de las especificaciones
            $lines = explode("\n", trim($block));
            $sku = '';
            $nombre = '';
            
            for ($j = 0; $j < min(6, count($lines)); $j++) {
                $line = trim($lines[$j]);
                // El SKU de Ronix suele ser 4 digitos seguido de letras opcionales (ej: 2820V, 2816LV, 3111V, 2890)
                if (preg_match('/^[0-9]{4}[A-Z]{0,2}$/', $line) || preg_match('/^[0-9]{4}\-[0-9]{2}V$/', $line)) {
                    $sku = $line;
                    if ($j > 0) {
                        $nombre = trim($lines[$j - 1]);
                        // A veces el nombre ocupa dos lineas
                        if ($j > 1 && !preg_match('/^[0-9]/', $lines[$j-2])) {
                            $nombre = trim($lines[$j - 2]) . ' ' . $nombre;
                        }
                    }
                    break;
                }
            }
            
            if ($sku) {
                $products[] = [
                    'codigo' => $sku,
                    'nombre' => $nombre,
                    'precio_bs' => null,
                    'precio_divisa' => $precio,
                    'descripcion' => $nombre,
                    'garantia' => null,
                    'condiciones' => null,
                    'tiempo_entrega' => null,
                    'raw_text' => implode("\n", array_slice($lines, -10)) . "\nPrecio: $ " . $precio,
                    'confidence' => null,
                ];
            }
        }

        return $products;
    }

    private function parseDecimal(string $value): float
    {
        $value = str_replace(',', '', $value);
        return (float) $value;
    }
}
