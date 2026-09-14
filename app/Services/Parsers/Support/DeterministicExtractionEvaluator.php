<?php

namespace App\Services\Parsers\Support;

class DeterministicExtractionEvaluator
{
    public const SUFFICIENT = 'sufficient';
    public const INSUFFICIENT = 'insufficient';
    public const NO_PRODUCT_PAGE = 'no_product_page';

    protected ProductPatternMatcher $matcher;

    public function __construct(?ProductPatternMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new ProductPatternMatcher();
    }

    /**
     * Evalúa si los productos obtenidos determinísticamente son suficientes,
     * si la página no contiene productos (informativa/portada), o si requiere fallback a IA.
     *
     * @param array $products Lista de productos devueltos por el parser determinístico
     * @param string $normalText Texto normal del PDF o página
     * @param string $redText Texto filtrado (ej: capa roja de OCR)
     * @return string SUFFICIENT | INSUFFICIENT | NO_PRODUCT_PAGE
     */
    public function evaluate(array $products, string $normalText, string $redText = ''): string
    {
        $combinedText = trim($normalText . "\n" . $redText);
        if (empty($combinedText)) {
            return self::NO_PRODUCT_PAGE;
        }

        // 1. Filtrar productos válidos (con SKU real, precio > 0 y nombre no vacío)
        $validProducts = array_filter($products, function ($p) {
            $codigo = trim($p['codigo'] ?? '');
            $precio = $p['precio_divisa'] ?? $p['precio_bs'] ?? null;
            $nombre = trim($p['nombre'] ?? '');

            if (empty($codigo) || preg_match('/^\d+\.\d+$/', $codigo)) {
                return false;
            }
            if ($precio === null || (float)$precio <= 0) {
                return false;
            }
            if (empty($nombre) || strlen($nombre) < 2) {
                return false;
            }
            return true;
        });

        $validCount = count($validProducts);

        // 2. Si hay productos válidos extraídos determinísticamente:
        if ($validCount > 0) {
            return self::SUFFICIENT;
        }

        // 3. Si se encontraron 0 productos válidos, determinar si la página tiene señales de productos o es informativa:
        if ($this->hasProductSignals($normalText, $redText)) {
            return self::INSUFFICIENT;
        }

        return self::NO_PRODUCT_PAGE;
    }

    /**
     * Determina si el texto contiene señales explícitas de contener productos o precios.
     */
    public function hasProductSignals(string $normalText, string $redText = ''): bool
    {
        // A. Señales de precios en texto rojo (OCR)
        if (!empty($redText)) {
            $redPairs = $this->matcher->extractCodePricePairs($redText);
            foreach ($redPairs as $rp) {
                if (!empty($rp['precio']) && $rp['precio'] > 0) {
                    return true;
                }
            }
            $redPrice = $this->matcher->extractPrice($redText);
            if ($redPrice !== null && $redPrice > 0) {
                return true;
            }
        }

        // B. Señales de pares código-precio con precio válido en texto normal
        $normalPairs = $this->matcher->extractCodePricePairsFromNormal($normalText);
        foreach ($normalPairs as $np) {
            if (!empty($np['precio']) && $np['precio'] > 0) {
                return true;
            }
        }

        // C. Presencia explícita de símbolo de moneda o precio con cifra (ej: $ 12.34 o USD 45.00)
        if (preg_match('/(\$|USD)\s*[\d.,]+/i', $normalText . ' ' . $redText)) {
            return true;
        }

        // D. Señales de especificaciones técnicas densas de herramientas (ej: *POTENCIA, *RPM, *VELOCIDAD, *BATERIA)
        if (preg_match('/(\*|\b)(POTENCIA|RPM|VELOCIDAD|BATER[IÍ]A|IMPACTO|VOLTAJE|M[AÁ]XIMO)\b/i', $normalText)) {
            return true;
        }

        return false;
    }
}
