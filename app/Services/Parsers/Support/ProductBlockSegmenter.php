<?php

namespace App\Services\Parsers\Support;

class ProductBlockSegmenter
{
    protected ProductPatternMatcher $matcher;

    public function __construct(?ProductPatternMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new ProductPatternMatcher();
    }

    /**
     * Busca el inicio del bloque hacia atrás desde la posición del código
     * (para el primer producto o segmentación simple).
     */
    public function findBlockStartSimple(array $lines, int $codePos): int
    {
        $start = $codePos;

        for ($i = $codePos - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ($this->matcher->looksLikeCode($line) || $this->isIgnoredLine($line)) {
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
     * Busca hacia atrás desde la posición del código para encontrar
     * el inicio lógico del bloque respetando otros códigos conocidos.
     */
    public function findBlockStart(
        array $lines,
        int $codePos,
        array $allCodigos = [],
        string $currentCodigo = ''
    ): int {
        $start = $codePos;

        for ($i = $codePos - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ($this->matcher->looksLikeCode($line)) {
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
     * Encuentra el índice de la última línea que pertenece al bloque del producto actual.
     */
    public function findBlockEnd(array $lines, int $codePos, ?int $nextCodePos): int
    {
        if ($nextCodePos === null) {
            return count($lines) - 1;
        }

        $nextBlockStart = $this->findBlockStartSimple($lines, $nextCodePos);

        return max($codePos, $nextBlockStart - 1);
    }

    /**
     * Delimita el bloque de texto en normalLines que corresponde a un código.
     *
     * @param string[] $normalLines
     * @return string[]
     */
    public function extractBlockForCode(
        array $normalLines,
        string $codigo,
        ?string $nextCodigo,
        array $allCodigos = []
    ): array {
        $codePos = $this->matcher->findCodePosition($normalLines, $codigo);

        if ($codePos === null) {
            $codePos = $this->matcher->findCodePositionFuzzy($normalLines, $codigo);
        }

        if ($codePos === null) {
            return [];
        }

        $blockStart = $this->findBlockStart($normalLines, $codePos, $allCodigos, $codigo);

        $blockEnd = count($normalLines) - 1;

        if ($nextCodigo !== null) {
            $nextPos = $this->matcher->findCodePosition($normalLines, $nextCodigo);
            if ($nextPos === null) {
                $nextPos = $this->matcher->findCodePositionFuzzy($normalLines, $nextCodigo);
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
     * Determina si una línea parece una especificación técnica o descripción.
     */
    public function looksLikeSpecLine(string $line): bool
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
     * Determina si una línea es ruido/encabezado ignorado.
     */
    public function isIgnoredLine(string $line): bool
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
}
