<?php

namespace App\Services\Parsers\Support;

class ProductAttributeExtractor
{
    protected ProductBlockSegmenter $segmenter;
    protected ProductTextNormalizer $normalizer;

    public function __construct(
        ?ProductBlockSegmenter $segmenter = null,
        ?ProductTextNormalizer $normalizer = null
    ) {
        $this->segmenter  = $segmenter ?? new ProductBlockSegmenter();
        $this->normalizer = $normalizer ?? new ProductTextNormalizer();
    }

    /**
     * Extrae el nombre del producto dentro de un bloque de líneas.
     */
    public function extractNameFromBlock(array $block, string $codigo): ?string
    {
        $nameParts = [];

        foreach ($block as $raw) {
            if (str_starts_with(trim($raw), '*') || $this->segmenter->looksLikeSpecLine($raw)) {
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

            if ($this->segmenter->isIgnoredLine($line)) {
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
     * Determina si una línea parece un nombre (mayúsculas/acentos).
     */
    public function looksLikeName(string $line): bool
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
     * Extrae la descripción técnica dentro de un bloque.
     */
    public function extractDescriptionFromBlock(array $block): ?string
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
     * Extrae la garantía dentro de un bloque.
     */
    public function extractWarrantyFromBlock(array $block): ?string
    {
        foreach ($block as $line) {
            if (stripos($line, 'garant') !== false) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extrae condiciones comerciales dentro de un bloque.
     */
    public function extractCondicionesFromBlock(array $block): ?string
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
     * Extrae tiempo de entrega dentro de un bloque.
     */
    public function extractEntregaFromBlock(array $block): ?string
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
     * Extrae precio dentro de un bloque (fallback).
     */
    public function extractPriceFromBlock(array $block): ?float
    {
        foreach ($block as $line) {
            $line = trim($line);

            if (preg_match('/\b(\d{1,6}(?:[.,]\d{1,2}))\b/', $line, $m)) {
                return $this->normalizer->parseDecimal($m[1]);
            }
        }

        return null;
    }

    /**
     * Extrae la garantía desde texto sin estructura de bloques.
     */
    public function extractWarranty(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            if (stripos($line, 'garant') !== false) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extrae el nombre desde texto rojo y texto normal sin estructura de bloques.
     */
    public function extractName(
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

                if ($clean === '' || $this->segmenter->isIgnoredLine($clean)) {
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

    /**
     * Extrae la descripción desde texto general sin estructura de bloques.
     */
    public function extractDescription(string $text): ?string
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
