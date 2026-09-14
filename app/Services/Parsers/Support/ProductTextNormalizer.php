<?php

namespace App\Services\Parsers\Support;

class ProductTextNormalizer
{
    /**
     * Reemplaza \r por \n, aplica trim a cada línea y elimina líneas vacías.
     */
    public function cleanText(string $text): string
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

    /**
     * Convierte un texto multilínea en un array de líneas no vacías.
     *
     * @return string[]
     */
    public function toLines(string $text): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode("\n", $text)),
                fn (string $l) => $l !== ''
            )
        );
    }

    /**
     * Convierte una cadena numérica a float manejando comas y puntos.
     */
    public function parseDecimal(string $value): float
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
}
