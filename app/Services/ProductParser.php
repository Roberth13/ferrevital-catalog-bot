<?php

namespace App\Services;

use App\Services\Parsers\CatalogParserInterface;
use App\Services\Parsers\Support\ProductAttributeExtractor;
use App\Services\Parsers\Support\ProductBlockSegmenter;
use App\Services\Parsers\Support\ProductPatternMatcher;
use App\Services\Parsers\Support\ProductTextNormalizer;

class ProductParser implements CatalogParserInterface
{
    protected ProductTextNormalizer $normalizer;
    protected ProductPatternMatcher $matcher;
    protected ProductBlockSegmenter $segmenter;
    protected ProductAttributeExtractor $extractor;

    public function __construct(
        ?ProductTextNormalizer $normalizer = null,
        ?ProductPatternMatcher $matcher = null,
        ?ProductBlockSegmenter $segmenter = null,
        ?ProductAttributeExtractor $extractor = null
    ) {
        $this->normalizer = $normalizer ?? new ProductTextNormalizer();
        $this->matcher    = $matcher ?? new ProductPatternMatcher($this->normalizer);
        $this->segmenter  = $segmenter ?? new ProductBlockSegmenter($this->matcher);
        $this->extractor  = $extractor ?? new ProductAttributeExtractor($this->segmenter, $this->normalizer);
    }

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
        return $this->segmenter->isIgnoredLine($line);
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

            $redPrice = $this->matcher->extractPrice($redText);
            if ($redPrice !== null) {
                foreach ($entries as &$entry) {
                    if ($entry['precio'] === null) {
                        $entry['precio'] = $redPrice;
                    }
                }
                unset($entry);
            }
        }

        if (empty($entries)) {
            return [];
        }

        $normalLines = $this->toLines($normalText);
        $total       = count($normalLines);

        if ($total === 0) {
            $products = [];
            foreach ($entries as $entry) {
                $nombre = $this->extractName($redText, '', $entry['codigo']) ?? $entry['codigo'];
                $products[] = [
                    'codigo'         => $entry['codigo'],
                    'nombre'         => $nombre,
                    'precio_bs'      => null,
                    'precio_divisa'  => $entry['precio'],
                    'descripcion'    => null,
                    'garantia'       => $this->extractWarranty($redText),
                    'condiciones'    => null,
                    'tiempo_entrega' => null,
                    'raw_text'       => $redText,
                    'confidence'     => null,
                ];
            }
            return $products;
        }

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

        $allFound = !in_array(null, $codePositions, true);

        if ($n === 1) {
            $blockStarts[0] = 0;
            $blockEnds[0]   = $total - 1;
        } elseif ($allFound) {
            for ($idx = 0; $idx < $n; $idx++) {
                $codePos     = $codePositions[$idx];
                $nextCodePos = $codePositions[$idx + 1] ?? null;

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
        } else {
            $chunkSize = (int) ceil($total / $n);
            for ($idx = 0; $idx < $n; $idx++) {
                $start = $idx * $chunkSize;
                $end   = ($idx === $n - 1) ? ($total - 1) : min($total - 1, (($idx + 1) * $chunkSize) - 1);
                $blockStarts[] = $start;
                $blockEnds[]   = $end;
            }
        }

        $products = [];

        foreach ($entries as $i => $entry) {
            $blockStart = $blockStarts[$i] ?? 0;
            $blockEnd   = $blockEnds[$i] ?? ($total - 1);

            $block = array_values(
                array_slice($normalLines, $blockStart, $blockEnd - $blockStart + 1)
            );

            $nombre = $this->extractNameFromBlock($block, $entry['codigo']);
            if ($nombre === null) {
                $nombre = $this->extractName($redText, implode("\n", $block), $entry['codigo']);
            }
            if ($nombre === null) {
                $nombre = $entry['codigo'];
            }

            $descripcion = $this->extractDescriptionFromBlock($block);
            if ($descripcion === null) {
                $descripcion = $this->extractDescription(implode("\n", $block));
            }

            $garantia    = $this->extractWarrantyFromBlock($block) ?? $this->extractWarranty($redText);
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
        return $this->segmenter->findBlockStartSimple($lines, $codePos);
    }

    private function looksLikeSpecLine(string $line): bool
    {
        return $this->segmenter->looksLikeSpecLine($line);
    }

    private function findBlockEnd(array $lines, int $codePos, ?int $nextCodePos): int
    {
        return $this->segmenter->findBlockEnd($lines, $codePos, $nextCodePos);
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
        return $this->matcher->extractCodePricePairs($redText);
    }

    private function looksLikeCodeAndPrice(
        string $line,
        ?string &$outCode,
        ?float  &$outPrice
    ): bool {
        return $this->matcher->looksLikeCodeAndPrice($line, $outCode, $outPrice);
    }

    private function extractCodePricePairsFromNormal(string $normalText): array
    {
        return $this->matcher->extractCodePricePairsFromNormal($normalText);
    }

    private function looksLikeCode(string $line): bool
    {
        return $this->matcher->looksLikeCode($line);
    }

    private function looksLikePrice(string $line): bool
    {
        return $this->matcher->looksLikePrice($line);
    }

    private function findCodePosition(array $lines, string $codigo): ?int
    {
        return $this->matcher->findCodePosition($lines, $codigo);
    }

    private function findCodePositionFuzzy(array $lines, string $codigo): ?int
    {
        return $this->matcher->findCodePositionFuzzy($lines, $codigo);
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
        return $this->segmenter->extractBlockForCode($normalLines, $codigo, $nextCodigo, $allCodigos);
    }

    private function findBlockStart(
        array $lines,
        int $codePos,
        array $allCodigos = [],
        string $currentCodigo = ''
    ): int {
        return $this->segmenter->findBlockStart($lines, $codePos, $allCodigos, $currentCodigo);
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
        return $this->extractor->extractNameFromBlock($block, $codigo);
    }

    private function looksLikeName(string $line): bool
    {
        return $this->extractor->looksLikeName($line);
    }

    private function extractDescriptionFromBlock(array $block): ?string
    {
        return $this->extractor->extractDescriptionFromBlock($block);
    }

    private function extractWarrantyFromBlock(array $block): ?string
    {
        return $this->extractor->extractWarrantyFromBlock($block);
    }

    private function extractCondicionesFromBlock(array $block): ?string
    {
        return $this->extractor->extractCondicionesFromBlock($block);
    }

    private function extractEntregaFromBlock(array $block): ?string
    {
        return $this->extractor->extractEntregaFromBlock($block);
    }

    private function extractPriceFromBlock(array $block): ?float
    {
        return $this->extractor->extractPriceFromBlock($block);
    }

    /**
     * Convierte un texto multilínea en un array de líneas no vacías.
     *
     * @return string[]
     */
    private function toLines(string $text): array
    {
        return $this->normalizer->toLines($text);
    }

    private function cleanText(string $text): string
    {
        return $this->normalizer->cleanText($text);
    }


    private function extractCode(string $text): ?string
    {
        return $this->matcher->extractCode($text);
    }

    private function extractPrice(string $text): ?float
    {
        return $this->matcher->extractPrice($text);
    }

    private function parseDecimal(string $value): float
    {
        return $this->normalizer->parseDecimal($value);
    }

    private function extractWarranty(string $text): ?string
    {
        return $this->extractor->extractWarranty($text);
    }

    private function extractName(
        string $redText,
        string $normalText,
        ?string $codigo
    ): ?string {
        return $this->extractor->extractName($redText, $normalText, $codigo);
    }

    private function extractDescription(string $text): ?string
    {
        return $this->extractor->extractDescription($text);
    }
}