<?php

namespace App\Services\Parsers;

class DongChengParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $normalText = $this->cleanText($normalText);
        $redText = $this->cleanText($redText);

        $entries = $this->extractCodePricePairs($redText);

        if (empty($entries)) {
            return [];
        }

        $normalLines = $this->toLines($normalText);
        $total = count($normalLines);

        // Map entries to their positions in the normal text
        $codePositions = [];
        foreach ($entries as $entry) {
            $pos = $this->findCodePosition($normalLines, $entry['codigo']);
            if ($pos === null) {
                // Remove parenthesis and extra parts for fuzzy search
                $baseCode = preg_replace('/\(.*\)/', '', $entry['codigo']);
                $pos = $this->findCodePositionFuzzy($normalLines, trim($baseCode));
            }
            $codePositions[] = $pos;
        }

        $blockStarts = [];
        $blockEnds = [];
        $n = count($entries);

        for ($idx = 0; $idx < $n; $idx++) {
            $codePos = $codePositions[$idx];
            $nextCodePos = $codePositions[$idx + 1] ?? null;

            if ($codePos === null) {
                // If we couldn't find the code in normal text, we still want to parse it
                $blockStarts[] = null;
                $blockEnds[] = null;
                continue;
            }

            if ($idx === 0) {
                $blockStarts[] = $this->findBlockStartSimple($normalLines, $codePos);
            } else {
                $prevBlockEnd = $blockEnds[$idx - 1];
                $blockStarts[] = ($prevBlockEnd !== null) ? $prevBlockEnd + 1 : $codePos;
            }

            if ($nextCodePos !== null) {
                $nextStart = $this->findBlockStartSimple($normalLines, $nextCodePos);
                $blockEnds[] = max($codePos, $nextStart - 1);
            } else {
                $blockEnds[] = $total - 1;
            }
        }

        $products = [];

        foreach ($entries as $i => $entry) {
            $codePos = $codePositions[$i];
            $blockStart = $blockStarts[$i];
            $blockEnd = $blockEnds[$i];

            $nombre = $entry['red_nombre'] ?? null;
            $descripcion = null;
            $garantia = $this->extractWarranty($redText);
            $condiciones = null;
            $entrega = null;
            $rawText = '';

            if ($blockStart !== null && $blockEnd !== null) {
                $block = array_values(
                    array_slice($normalLines, $blockStart, $blockEnd - $blockStart + 1)
                );
                
                $extractedName = $this->extractNameFromBlock($block, $entry['codigo']);
                if ($extractedName) {
                    // Combine red text name and normal text name
                    $nombre = trim(($nombre ? $nombre . ' ' : '') . $extractedName);
                }

                $descripcion = $this->extractDescriptionFromBlock($block);
                $rawText = implode("\n", $block);
            }

            $products[] = [
                'codigo' => $entry['codigo'],
                'nombre' => $nombre ?: 'Producto Dong Cheng',
                'precio_bs' => null,
                'precio_divisa' => $entry['precio'],
                'descripcion' => $descripcion,
                'garantia' => $garantia,
                'condiciones' => $condiciones,
                'tiempo_entrega' => $entrega,
                'raw_text' => $rawText,
                'confidence' => null,
            ];
        }

        return $products;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return preg_replace('/\n{3,}/', "\n\n", trim($text));
    }

    private function toLines(string $text): array
    {
        return array_values(
            array_filter(
                array_map(
                    fn (string $line) => trim($line),
                    explode("\n", $text)
                ),
                fn (string $line) => $line !== ''
            )
        );
    }

    private function extractCodePricePairs(string $redText): array
    {
        $lines = $this->toLines($redText);
        $pairs = [];
        
        $currentName = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Ignore common non-product lines
            if (stripos($line, 'Dong Cheng') !== false || stripos($line, 'Garantia') !== false) {
                continue;
            }

            if ($this->looksLikePrice($line)) {
                // If we hit a price, look back to find the code and name
                $precio = $this->parseDecimal($line);
                
                $codigo = null;
                $redNombre = implode(" ", $currentName);
                
                // Backtrack to find the code
                for ($j = count($pairs); $j < $i; $j++) {
                    if (isset($lines[$j]) && $this->looksLikeCode($lines[$j])) {
                        $codigo = trim($lines[$j]);
                        // Remove code from redNombre
                        $redNombre = trim(str_replace($codigo, '', $redNombre));
                        break;
                    }
                }
                
                if ($codigo) {
                    $pairs[] = [
                        'codigo' => $codigo, 
                        'precio' => $precio,
                        'red_nombre' => $redNombre
                    ];
                }
                
                $currentName = [];
            } else {
                if (!$this->looksLikeCode($line)) {
                    $currentName[] = $line;
                }
            }
        }

        return $pairs;
    }

    private function looksLikeCode(string $line): bool
    {
        $line = trim($line);
        // Remove parenthesis and their contents for checking
        $baseStr = preg_replace('/\(.*?\)/', '', $line);
        $normalized = preg_replace('/\s+/', '', $baseStr);

        if (strlen($normalized) < 4 || strlen($normalized) > 25) {
            return false;
        }
        if (!preg_match('/[A-Za-z]/', $normalized)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $normalized)) {
            return false;
        }
        
        // Allows alphanumeric with dashes, slashes, periods, and parenthesis at the end
        return (bool) preg_match('/^[A-Z0-9][A-Z0-9.\-_\/]{3,24}(?:\s*\(.*?\))?$/i', $line);
    }

    private function looksLikePrice(string $line): bool
    {
        $line = trim($line);
        return (bool) preg_match('/^\$?\s*\d{1,6}(?:[.,]\d{1,2})?\s*$/', $line);
    }

    private function parseDecimal(string $value): float
    {
        $value = preg_replace('/[^\d.,]/', '', $value);
        if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
            $value = str_replace(',', '', $value);
        } elseif (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }
        return (float) $value;
    }

    private function extractWarranty(string $text): ?string
    {
        if (preg_match('/(\d+)\s*(?:año|meses)\s*de\s*garantia/i', $text, $matches)) {
            return trim($matches[0]);
        }
        return null;
    }

    private function findCodePosition(array $lines, string $codigo): ?int
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, $codigo) !== false) {
                return $i;
            }
        }
        return null;
    }

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

    private function isIgnoredLine(string $line): bool
    {
        $ignored = ['N/A', 'Unit Uni/Min Uni/Pack Precio', 'Dong Cheng'];
        if (in_array($line, $ignored, true)) {
            return true;
        }
        return preg_match('/^www\./i', $line) === 1;
    }

    private function looksLikeSpecLine(string $line): bool
    {
        if (str_starts_with(trim($line), '*')) return true;
        $upper = strtoupper($line);
        $keywords = [
            'VELOCIDAD', 'FRECUENCIA', 'DIAMETRO', 'POTENCIA', 'TENSION',
            'VOLTAJE', 'CAPACIDAD', 'INCLUYE', 'CARGADOR', 'BATERIA',
            'ACERO', 'HORMIGON', 'MADERA', 'PERFORACION', 'MM,'
        ];
        foreach ($keywords as $kw) {
            if (str_contains($upper, $kw)) return true;
        }
        if (preg_match('/\d+\s*MM/i', $line)) return true;
        return false;
    }

    private function extractNameFromBlock(array $block, string $codigo): ?string
    {
        $nameParts = [];
        $baseCode = trim(preg_replace('/\(.*?\)/', '', $codigo));

        foreach ($block as $raw) {
            if (str_starts_with(trim($raw), '*') || $this->looksLikeSpecLine($raw)) {
                break;
            }

            $line = trim($raw, " -*|");
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '') continue;

            if (stripos($line, $baseCode) !== false) {
                $fragment = trim(preg_replace('/\b' . preg_quote($baseCode, '/') . '\b.*$/i', '', $line));
                $fragment = trim($fragment, " -*|()");
                $fragment = trim(preg_replace('/\s+/', ' ', $fragment));
                if ($fragment !== '' && $this->looksLikeName($fragment)) {
                    $nameParts[] = $fragment;
                }
                continue;
            }

            if ($this->isIgnoredLine($line)) continue;
            if (preg_match('/^\d[\d.,\s]+$/', $line)) continue;
            if (preg_match('/^www\./i', $line)) continue;
            
            if ($this->looksLikeName($line)) {
                $nameParts[] = $line;
            }
        }
        
        return empty($nameParts) ? null : trim(implode(' ', $nameParts));
    }

    private function looksLikeName(string $line): bool
    {
        $onlyLetters = preg_replace('/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü]/u', '', $line);
        if (strlen($onlyLetters) < 3) return false;
        
        $upperCount = preg_match_all('/[A-ZÁÉÍÓÚÑÜ]/u', $onlyLetters);
        $totalCount = mb_strlen($onlyLetters);
        
        return ($upperCount / $totalCount) >= 0.5; // Lowered to 0.5 since Dong Cheng might have mixed cases
    }

    private function extractDescriptionFromBlock(array $block): ?string
    {
        $lines = [];
        foreach ($block as $line) {
            if (str_starts_with($line, '*') || $this->looksLikeSpecLine($line)) {
                $lines[] = trim($line);
            }
        }
        return empty($lines) ? null : implode("\n", $lines);
    }
}
