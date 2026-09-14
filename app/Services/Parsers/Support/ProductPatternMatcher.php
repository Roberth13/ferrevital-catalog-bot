<?php

namespace App\Services\Parsers\Support;

class ProductPatternMatcher
{
    protected ProductTextNormalizer $normalizer;

    public function __construct(?ProductTextNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new ProductTextNormalizer();
    }

    public function extractCode(string $text): ?string
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

    public function extractPrice(string $text): ?float
    {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if (preg_match(
                '/\b(\d{1,6}(?:[.,]\d{1,2}))\b/',
                $line,
                $matches
            )) {
                return $this->normalizer->parseDecimal($matches[1]);
            }
        }

        return null;
    }

    public function looksLikeCode(string $line): bool
    {
        $line = trim($line);

        $normalized = preg_replace('/\s+/', '', $line);

        if (strlen($normalized) < 4 || strlen($normalized) > 30) {
            return false;
        }

        if (!preg_match('/[A-Za-z]/', $normalized)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $normalized)) {
            return false;
        }

        $cleanedWords = preg_replace('/\s*\([^\)]*\)/', '', $line);
        if (str_word_count($cleanedWords) > 2) {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9][A-Z0-9.\-_\/]{2,24}(\s*\([A-Z0-9\s.\-_]+\))?$/i', $line);
    }

    public function looksLikePrice(string $line): bool
    {
        $line = trim($line);

        return (bool) preg_match(
            '/^\$?\s*\d{1,6}(?:[.,]\d{1,2})?\s*$/',
            $line
        );
    }

    public function looksLikeCodeAndPrice(
        string $line,
        ?string &$outCode,
        ?float &$outPrice
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
        $outPrice = $this->normalizer->parseDecimal($m[2]);

        return true;
    }

    public function extractCodePricePairs(string $redText): array
    {
        $lines = $this->normalizer->toLines($redText);
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

            for ($k = $i + 1; $k < min(count($lines), $i + 4); $k++) {
                if ($this->looksLikePrice($lines[$k])) {
                    $precio = $this->normalizer->parseDecimal($lines[$k]);
                    $i = $k;
                    break;
                }
                if ($this->looksLikeCode($lines[$k])) {
                    break;
                }
            }

            $pairs[] = ['codigo' => $codigo, 'precio' => $precio];
        }

        return $pairs;
    }

    public function extractCodePricePairsFromNormal(string $normalText): array
    {
        $lines = $this->normalizer->toLines($normalText);
        $pairs = [];

        foreach ($lines as $i => $line) {
            $codigo = null;
            if ($this->looksLikeCode($line)) {
                $codigo = strtoupper(trim($line));
            } elseif (preg_match('/\b([A-Z]{2,}[A-Z0-9.\-_\/]{1,15}\d[A-Z0-9.\-_\/]*)\b/i', $line, $m)) {
                $candidate = strtoupper(trim($m[1], ' .-_/'));
                $blacklisted = ['CARGADOR', 'BATERIA', 'VOLTAJE', 'TENSION', 'BATERIAS', 'POTENCIA', 'REF', 'WWW', 'CARACTERISTICAS', 'PRODUCTO', 'GARANTIA', 'IONES', 'LITIO', 'MAX', 'MIN', 'RPM'];
                if (!in_array($candidate, $blacklisted, true) && strlen($candidate) >= 4) {
                    $codigo = $candidate;
                }
            }

            if ($codigo === null) {
                continue;
            }

            $precio = null;

            for ($j = $i + 1; $j < min($i + 6, count($lines)); $j++) {
                if ($this->looksLikePrice($lines[$j])) {
                    $precio = $this->normalizer->parseDecimal($lines[$j]);
                    break;
                }
            }

            $pairs[] = ['codigo' => $codigo, 'precio' => $precio];
        }

        return $pairs;
    }

    public function findCodePosition(array $lines, string $codigo): ?int
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, $codigo) !== false) {
                return $i;
            }
        }

        return null;
    }

    public function findCodePositionFuzzy(array $lines, string $codigo): ?int
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
}
