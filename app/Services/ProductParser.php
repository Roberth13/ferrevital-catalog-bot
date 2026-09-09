<?php

namespace App\Services;

use App\Services\Parsers\CatalogParserInterface;

class ProductParser implements CatalogParserInterface
{
    public function parse(string $text, string $redText = '', ?int $catalogId = null): array
    {
        $lines = $this->normalizeLines($text);

        $products = [];

        foreach ($lines as $index => $line) {
            if (!$this->isProductCode($line)) {
                continue;
            }

            $code = $line;

            $name = $this->findProductName($lines, $index);

            if ($name === null) {
                continue;
            }

            $price = $this->findPrice($lines, $index);

            $products[] = [
                'codigo' => $code,
                'nombre' => $name,
                'precio_bs' => null,
                'precio_divisa' => $price,
                'descripcion' => null,
                'garantia' => null,
                'condiciones' => null,
                'tiempo_entrega' => null,
                'raw_text' => $this->extractRawProductBlock($lines, $index),
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function normalizeLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = explode("\n", $text);

        return array_values(
            array_filter(
                array_map(
                    fn (string $line) => trim($line),
                    $lines
                ),
                fn (string $line) => $line !== ''
            )
        );
    }

    private function isProductCode(string $line): bool
    {
        return preg_match('/^[A-Z0-9][A-Z0-9._-]{5,}$/', $line) === 1;
    }

    private function findProductName(array $lines, int $codeIndex): ?string
    {
        if ($codeIndex === 0) {
            return null;
        }

        for ($i = $codeIndex - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ($this->isProductCode($line)) {
                break;
            }

            if ($this->isIgnoredLine($line)) {
                continue;
            }

            if ($this->looksLikeProductName($line)) {
                return $line;
            }
        }

        return null;
    }

    private function findPrice(array $lines, int $codeIndex): ?float
    {
        $maxSearch = min($codeIndex + 8, count($lines));

        for ($i = $codeIndex + 1; $i < $maxSearch; $i++) {
            $line = $lines[$i];

            if (
                preg_match(
                    '/(?:\$|USD)?\s*([\d.,]+)\s*$/i',
                    $line,
                    $matches
                )
            ) {
                return $this->parseDecimal($matches[1]);
            }
        }

        return null;
    }
    private function extractCodes(string $text): array
    {
        preg_match_all(
            '/\b[A-Z]{2,}[A-Z0-9]*[-]?[A-Z0-9]{2,}\b/i',
            strtoupper($text),
            $matches
        );

        return array_values(array_unique($matches[0]));
    }

    private function looksLikeProductName(string $line): bool
    {
        if ($this->isIgnoredLine($line)) {
            return false;
        }

        if ($this->isProductCode($line)) {
            return false;
        }

        if (preg_match('/^www\./i', $line)) {
            return false;
        }

        if (preg_match('/^(Unit|Uni\/Min|Uni\/Pack|Precio)$/i', $line)) {
            return false;
        }

        return strlen($line) >= 5;
    }

    private function isIgnoredLine(string $line): bool
    {
        $ignored = [
            'N/A',
            'Unit Uni/Min Uni/Pack Precio',
        ];

        if (in_array($line, $ignored, true)) {
            return true;
        }

        return preg_match('/^www\./i', $line) === 1;
    }

    private function extractRawProductBlock(
        array $lines,
        int $codeIndex
    ): ?string {
        $start = max(0, $codeIndex - 1);

        $end = $codeIndex;

        for ($i = $codeIndex + 1; $i < count($lines); $i++) {
            $end = $i;

            if ($this->isPriceLine($lines[$i])) {
                break;
            }
        }

        return implode("\n", array_slice(
            $lines,
            $start,
            $end - $start + 1
        ));
    }

    private function isPriceLine(string $line): bool
    {
        return preg_match(
            '/\$\s*[\d.,]+\s*$/',
            $line
        ) === 1;
    }

    public function parseOcr(
        string $normalText,
        string $redText
    ): array {
        $normalText = $this->cleanText($normalText);
        $redText = $this->cleanText($redText);

        $codigo = $this->extractCode($redText);
        $precio = $this->extractPrice($redText);
        $garantia = $this->extractWarranty($redText);

        $nombre = $this->extractName(
            $redText,
            $normalText,
            $codigo
        );

        $descripcion = $this->extractDescription($normalText);

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'precio_bs' => null,
            'precio_divisa' => $precio,
            'descripcion' => $descripcion,
            'garantia' => $garantia,
            'condiciones' => null,
            'tiempo_entrega' => null,
            'raw_text' => $normalText,
            'confidence' => null,
        ];
    }

    public function parseMultipleOcr(
        string $normalText,
        string $redText
    ): array {
        $normalText = $this->cleanText($normalText);
        $redText    = $this->cleanText($redText);

        $entries = $this->extractCodePricePairs($redText);

        if (empty($entries)) {
            $entries = $this->extractCodePricePairsFromNormal($normalText);
        }

        if (empty($entries)) {
            return [];
        }

        $normalLines = $this->toLines($normalText);
        $total       = count($normalLines);

        $codePositions = [];
        foreach ($entries as $entry) {
            $pos = $this->findCodePosition($normalLines, $entry['codigo']);
            if ($pos === null) {
                $pos = $this->findCodePositionFuzzy($normalLines, $entry['codigo']);
            }
            $codePositions[] = $pos;
        }

        $blockStarts = [];
        $blockEnds   = [];
        $n           = count($entries);

        for ($idx = 0; $idx < $n; $idx++) {
            $codePos     = $codePositions[$idx];
            $nextCodePos = $codePositions[$idx + 1] ?? null;

            if ($codePos === null) {
                $blockStarts[] = null;
                $blockEnds[]   = null;
                continue;
            }

            if ($idx === 0) {
                $blockStarts[] = $this->findBlockStartSimple($normalLines, $codePos);
            } else {
                $prevBlockEnd  = $blockEnds[$idx - 1];
                $blockStarts[] = ($prevBlockEnd !== null) ? $prevBlockEnd + 1 : $codePos;
            }

            if ($nextCodePos !== null) {
                $nextStart   = $this->findBlockStartSimple($normalLines, $nextCodePos);
                $blockEnds[] = max($codePos, $nextStart - 1);
            } else {
                $blockEnds[] = $total - 1;
            }
        }

        $products = [];

        foreach ($entries as $i => $entry) {
            $codePos    = $codePositions[$i];
            $blockStart = $blockStarts[$i];
            $blockEnd   = $blockEnds[$i];

            if ($codePos === null || $blockStart === null || $blockEnd === null) {
                continue;
            }

            $block = array_values(
                array_slice($normalLines, $blockStart, $blockEnd - $blockStart + 1)
            );

            $nombre      = $this->extractNameFromBlock($block, $entry['codigo']);
            $descripcion = $this->extractDescriptionFromBlock($block);
            $garantia    = $this->extractWarrantyFromBlock($block);
            $condiciones = $this->extractCondicionesFromBlock($block);
            $entrega     = $this->extractEntregaFromBlock($block);

            $precio = $entry['precio'] ?? $this->extractPriceFromBlock($block);

            $products[] = [
                'codigo'         => $entry['codigo'],
                'nombre'         => $nombre,
                'precio_bs'      => null,
                'precio_divisa'  => $precio,
                'descripcion'    => $descripcion,
                'garantia'       => $garantia,
                'condiciones'    => $condiciones,
                'tiempo_entrega' => $entrega,
                'raw_text'       => implode("\n", $block),
                'confidence'     => null,
            ];
        }

        return $products;
    }

    /**
     * Busca el inicio del bloque hacia atrás desde la posición del código
     * (solo para el primer producto, donde no hay código previo como límite).
     */
    private function findBlockStartSimple(array $lines, int $codePos): int
    {
        $start = $codePos;

        for ($i = $codePos - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ($this->looksLikeCode($line) || $this->isIgnoredLine($line)) {
                break;
            }

            if (preg_match('/^[\d\s*]+$/', $line)) {
                break;
            }

            if ($this->looksLikeSpecLine($line)) {
                break;
            }

            $start = $i;
        }

        return max(0, $start);
    }

    /**
     * Determina si una línea parece una especificación técnica o descripción,
     * no un nombre de producto.
     */
    private function looksLikeSpecLine(string $line): bool
    {
        if (str_starts_with(trim($line), '*')) {
            return true;
        }

        $upper = strtoupper($line);

        $keywords = [
            'VELOCIDAD', 'FRECUENCIA', 'DIAMETRO', 'POTENCIA', 'TENSION',
            'VOLTAJE', 'CAPACIDAD', 'INCLUYE', 'CARGADOR', 'BATERIA',
            'ACERO', 'HORMIGON', 'MADERA', 'PERFORACION', 'MM,', 'MM Y',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($upper, $kw)) {
                return true;
            }
        }

        if (preg_match('/\d+\s*MM/i', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Encuentra el índice de la última línea que pertenece al bloque del producto actual,
     * deteniéndose justo antes de donde el siguiente código aparece o donde el próximo
     * bloque comienza.
     */
    private function findBlockEnd(array $lines, int $codePos, ?int $nextCodePos): int
    {
        if ($nextCodePos === null) {
            return count($lines) - 1;
        }

        $nextBlockStart = $this->findBlockStartSimple($lines, $nextCodePos);

        return max($codePos, $nextBlockStart - 1);
    }

    /**
     * Extrae pares código+precio del texto rojo.
     *
     * El texto rojo suele tener el código en una línea y el precio
     * en la línea inmediatamente siguiente (ej: "DZJ02-13\n48,97").
     *
     * @return array<int, array{codigo: string, precio: ?float}>
     */
    private function extractCodePricePairs(string $redText): array
    {
        $lines = $this->toLines($redText);
        $pairs = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if ($this->looksLikeCodeAndPrice($line, $codigo, $precio)) {
                $pairs[] = ['codigo' => strtoupper($codigo), 'precio' => $precio];
                continue;
            }

            if (!$this->looksLikeCode($line)) {
                continue;
            }

            $codigo = strtoupper(trim($line));
            $precio = null;

            $next = $lines[$i + 1] ?? null;
            if ($next !== null && $this->looksLikePrice($next)) {
                $precio = $this->parseDecimal($next);
                $i++;
            }

            $pairs[] = ['codigo' => $codigo, 'precio' => $precio];
        }

        return $pairs;
    }

    /**
     * Fix 4: detecta código y precio en la misma línea.
     * Ej: "DZJ02-13 48.97" o "DZJ02-13\t48,97".
     */
    private function looksLikeCodeAndPrice(
        string $line,
        ?string &$outCode,
        ?float  &$outPrice
    ): bool {
        $line = trim($line);

        if (!preg_match(
            '/^([A-Z0-9][A-Z0-9.\-_\/]{3,24})\s+([\d.,]{3,10})\s*$/i',
            $line,
            $m
        )) {
            return false;
        }

        if (!$this->looksLikeCode($m[1])) {
            return false;
        }

        if (!$this->looksLikePrice($m[2])) {
            return false;
        }

        $outCode  = $m[1];
        $outPrice = $this->parseDecimal($m[2]);

        return true;
    }

    /**
     * Fallback: busca códigos en el texto normal cuando el rojo no los tiene.
     *
     * @return array<int, array{codigo: string, precio: ?float}>
     */
    private function extractCodePricePairsFromNormal(string $normalText): array
    {
        $lines = $this->toLines($normalText);
        $pairs = [];

        foreach ($lines as $i => $line) {
            if (!$this->looksLikeCode($line)) {
                continue;
            }

            $codigo = strtoupper(trim($line));
            $precio = null;

            for ($j = $i + 1; $j < min($i + 6, count($lines)); $j++) {
                if ($this->looksLikePrice($lines[$j])) {
                    $precio = $this->parseDecimal($lines[$j]);
                    break;
                }
            }

            $pairs[] = ['codigo' => $codigo, 'precio' => $precio];
        }

        return $pairs;
    }

    /**
     * Determina si una línea parece un código de producto.
     *
     * Requisitos mínimos:
     * - Al menos 4 caracteres.
     * - Contiene letras Y dígitos (o un guion separador).
     * - No es una frase larga (máx. 20 chars para evitar frases OCR).
     */
    private function looksLikeCode(string $line): bool
    {
        $line = trim($line);

        $normalized = preg_replace('/\s+/', '', $line);

        if (strlen($normalized) < 4 || strlen($normalized) > 25) {
            return false;
        }

        if (!preg_match('/[A-Za-z]/', $normalized)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $normalized)) {
            return false;
        }

        if (str_word_count($line) > 2) {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9][A-Z0-9.\-_\/]{3,24}$/i', $normalized);
    }

    /**
     * Determina si una línea parece un precio numérico.
     *
     * Acepta formatos: "48,97" / "48.97" / "1.234,56" / "$48.97"
     */
    private function looksLikePrice(string $line): bool
    {
        $line = trim($line);

        return (bool) preg_match(
            '/^\$?\s*\d{1,6}(?:[.,]\d{1,2})?\s*$/',
            $line
        );
    }

    /**
     * Delimita el bloque de texto en normalLines que corresponde a un código.
     *
     * Estrategia:
     * 1. Busca la primera aparición del código en normalLines.
     * 2. Toma todas las líneas desde la más cercana al inicio del producto
     *    (buscando hacia atrás hasta la línea anterior al bloque previo)
     *    hasta justo antes de donde aparece el siguiente código.
     *
     * @param  string[] $normalLines
     * @return string[]
     */
    private function extractBlockForCode(
        array $normalLines,
        string $codigo,
        ?string $nextCodigo,
        array $allCodigos = []
    ): array {
        $codePos = $this->findCodePosition($normalLines, $codigo);

        if ($codePos === null) {
            $codePos = $this->findCodePositionFuzzy($normalLines, $codigo);
        }

        if ($codePos === null) {
            return [];
        }

        $blockStart = $this->findBlockStart($normalLines, $codePos, $allCodigos, $codigo);

        $blockEnd = count($normalLines) - 1;

        if ($nextCodigo !== null) {
            $nextPos = $this->findCodePosition($normalLines, $nextCodigo);
            if ($nextPos === null) {
                $nextPos = $this->findCodePositionFuzzy($normalLines, $nextCodigo);
            }
            if ($nextPos !== null) {
                $nextBlockStart = $this->findBlockStart($normalLines, $nextPos, $allCodigos, $nextCodigo);
                $blockEnd       = max($codePos, $nextBlockStart - 1);
            }
        }

        return array_values(
            array_slice($normalLines, $blockStart, $blockEnd - $blockStart + 1)
        );
    }

    /**
     * Fix 5: búsqueda fuzzy del código ignorando guiones y espacios.
     * Útil cuando el OCR fragmenta "DZJ02-13" como "DZJ02 13".
     */
    private function findCodePositionFuzzy(array $lines, string $codigo): ?int
    {
        $needle = strtoupper(preg_replace('/[\s\-]/', '', $codigo));

        foreach ($lines as $i => $line) {
            $haystack = strtoupper(preg_replace('/[\s\-]/', '', $line));
            if (str_contains($haystack, $needle)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Encuentra la posición (índice) de la primera línea que contiene el código.
     */
    private function findCodePosition(array $lines, string $codigo): ?int
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, $codigo) !== false) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Busca hacia atrás desde la posición del código para encontrar
     * el inicio lógico del bloque (primera línea que parece nombre/título).
     */
    private function findBlockStart(
        array $lines,
        int $codePos,
        array $allCodigos = [],
        string $currentCodigo = ''
    ): int {
        $start = $codePos;

        for ($i = $codePos - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ($this->looksLikeCode($line)) {
                break;
            }

            if ($this->isIgnoredLine($line)) {
                continue;
            }

            $hasForeignCode = false;
            foreach ($allCodigos as $otherCode) {
                if ($otherCode === $currentCodigo) {
                    continue;
                }
                if (stripos($line, $otherCode) !== false) {
                    $hasForeignCode = true;
                    break;
                }
            }

            if ($hasForeignCode) {
                $start = $i + 1;
                break;
            }

            $start = $i;
        }

        return max(0, $start);
    }

    /**
     * Extrae el nombre del producto dentro de un bloque de líneas.
     *
     * Heurística:
     * - Las primeras líneas en mayúsculas antes de los asteriscos/specs.
     * - Excluye el propio código y líneas ignoradas.
     */
    private function extractNameFromBlock(array $block, string $codigo): ?string
    {
        $nameParts = [];

        foreach ($block as $raw) {
            if (str_starts_with(trim($raw), '*') || $this->looksLikeSpecLine($raw)) {
                break;
            }

            $line = trim($raw, " -*|");
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '') {
                continue;
            }

            if (stripos($line, $codigo) !== false) {
                $fragment = trim(preg_replace(
                    '/\b' . preg_quote($codigo, '/') . '\b.*$/i',
                    '',
                    $line
                ));
                $fragment = trim($fragment, " -*|()");
                $fragment = trim(preg_replace('/\s+/', ' ', $fragment));

                if ($fragment !== '' && $this->looksLikeName($fragment)) {
                    $nameParts[] = $fragment;
                }
                continue;
            }

            if ($this->isIgnoredLine($line)) {
                continue;
            }


            if (preg_match('/^\d[\d.,\s]+$/', $line)) {
                continue;
            }

            if (preg_match('/^www\./i', $line)) {
                continue;
            }

            if (preg_match('/^(REF|REF\s*:)/i', $line)) {
                continue;
            }

            if ($this->looksLikeName($line)) {
                $nameParts[] = $line;
            }

            if (count($nameParts) >= 3) {
                break;
            }
        }

        if (empty($nameParts)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $nameParts)));
    }

    /**
     * Fix 3: heurística genérica para determinar si una línea parece un nombre.
     * Acepta mayúsculas puras, mayúsculas con acentos y mezclas OCR.
     */
    private function looksLikeName(string $line): bool
    {
        $onlyLetters = preg_replace('/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü]/u', '', $line);

        if (strlen($onlyLetters) < 3) {
            return false;
        }

        $upperCount = preg_match_all('/[A-ZÁÉÍÓÚÑÜ]/u', $onlyLetters);
        $totalCount = mb_strlen($onlyLetters);

        return ($upperCount / $totalCount) >= 0.6;
    }

    /**
     * Extrae la descripción (líneas con asterisco o palabras clave técnicas)
     * dentro del bloque.
     */
    private function extractDescriptionFromBlock(array $block): ?string
    {
        $lines = [];

        foreach ($block as $line) {
            if (
                str_starts_with($line, '*') ||
                str_contains(strtoupper($line), 'VELOCIDAD') ||
                str_contains(strtoupper($line), 'FRECUENCIA') ||
                str_contains(strtoupper($line), 'DIAMETRO') ||
                str_contains(strtoupper($line), 'POTENCIA') ||
                str_contains(strtoupper($line), 'INCLUYE') ||
                str_contains(strtoupper($line), 'CARGADOR') ||
                str_contains(strtoupper($line), 'BATERIA') ||
                str_contains(strtoupper($line), 'CAPACIDAD') ||
                str_contains(strtoupper($line), 'TENSION') ||
                str_contains(strtoupper($line), 'VOLTAJE')
            ) {
                $lines[] = trim($line);
            }
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /**
     * Extrae la garantía dentro del bloque.
     */
    private function extractWarrantyFromBlock(array $block): ?string
    {
        foreach ($block as $line) {
            if (stripos($line, 'garant') !== false) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extrae condiciones comerciales dentro del bloque.
     */
    private function extractCondicionesFromBlock(array $block): ?string
    {
        foreach ($block as $line) {
            if (
                stripos($line, 'condici') !== false ||
                stripos($line, 'pago') !== false ||
                stripos($line, 'contado') !== false ||
                stripos($line, 'credito') !== false
            ) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extrae tiempo de entrega dentro del bloque.
     */
    private function extractEntregaFromBlock(array $block): ?string
    {
        foreach ($block as $line) {
            if (
                stripos($line, 'entrega') !== false ||
                stripos($line, 'despacho') !== false ||
                stripos($line, 'días') !== false ||
                stripos($line, 'dias') !== false
            ) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extrae el primer precio que aparezca dentro del bloque
     * (fallback cuando el redText no proporcionó precio para este código).
     */
    private function extractPriceFromBlock(array $block): ?float
    {
        foreach ($block as $line) {
            $line = trim($line);

            if (preg_match('/\b(\d{1,6}(?:[.,]\d{1,2}))\b/', $line, $m)) {
                return $this->parseDecimal($m[1]);
            }
        }

        return null;
    }

    /**
     * Convierte un texto multilínea en un array de líneas no vacías.
     *
     * @return string[]
     */
    private function toLines(string $text): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode("\n", $text)),
                fn (string $l) => $l !== ''
            )
        );
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\r", "\n", $text);

        $lines = array_map(
            fn ($line) => trim($line),
            explode("\n", $text)
        );

        $lines = array_filter(
            $lines,
            fn ($line) => $line !== ''
        );

        return implode("\n", $lines);
    }

    private function extractCode(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            // El código debe contener letras y al menos un número.
            if (
                preg_match(
                    '/\b([A-Z]{2,}[A-Z0-9-]*\d[A-Z0-9-]*)\b/i',
                    $line,
                    $matches
                )
            ) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    private function extractPrice(string $text): ?float
    {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if (preg_match(
                '/\b(\d{1,6}(?:[.,]\d{1,2}))\b/',
                $line,
                $matches
            )) {
                return $this->parseDecimal($matches[1]);
            }
        }

        return null;
    }

    private function parseDecimal(string $value): float
    {
        $value = trim($value);
        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    private function extractWarranty(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            if (stripos($line, 'garant') !== false) {
                return trim($line);
            }
        }

        return null;
    }

    private function extractName(
        string $redText,
        string $normalText,
        ?string $codigo
    ): ?string {
        $redLines = array_values(
            array_filter(
                array_map('trim', explode("\n", $redText))
            )
        );

        $normalLines = array_values(
            array_filter(
                array_map('trim', explode("\n", $normalText))
            )
        );

        $nameParts = [];

        if ($codigo !== null) {
            foreach ($redLines as $line) {
                if (stripos($line, $codigo) !== false) {
                    break;
                }

                if ($line !== '') {
                    $nameParts[] = $line;
                }
            }
        }

        if (empty($nameParts)) {
            foreach ($normalLines as $line) {
                $clean = trim($line, " -*|");
                $clean = trim(preg_replace('/\s+/', ' ', $clean));

                if ($clean === '' || $this->isIgnoredLine($clean)) {
                    continue;
                }

                if ($codigo !== null && stripos($clean, $codigo) !== false) {
                    continue;
                }

                if (preg_match('/^(REF|REF\s*:|www\.)/i', $clean)) {
                    continue;
                }

                if (preg_match('/^\d[\d.,\s]+$/', $clean)) {
                    continue;
                }

                if (str_starts_with($clean, '*')) {
                    continue;
                }

                if ($this->looksLikeName($clean)) {
                    $nameParts[] = $clean;

                    if (count($nameParts) >= 2) {
                        break;
                    }
                }
            }
        }

        if (empty($nameParts)) {
            return null;
        }

        return trim(
            preg_replace('/\s+/', ' ', implode(' ', $nameParts))
        );
    }

    private function extractDescription(string $text): ?string
    {
        $lines = array_values(
            array_filter(
                array_map('trim', explode("\n", $text))
            )
        );

        $description = [];

        foreach ($lines as $line) {
            if (
                str_starts_with($line, '*') ||
                str_contains(strtoupper($line), 'INCLUYE') ||
                str_contains(strtoupper($line), 'CARGADOR')
            ) {
                $description[] = $line;
            }
        }

        if (empty($description)) {
            return $text ?: null;
        }

        return implode("\n", $description);
    }
}