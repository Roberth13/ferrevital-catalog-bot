<?php

namespace App\Services\Parsers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiCatalogParser implements CatalogParserInterface
{
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array
    {
        $text = trim($normalText . "\n" . $redText);
        
        if (empty($text)) {
            return [];
        }

        // Dividir el texto en chunks si es muy grande (ej: > 50,000 caracteres)
        $maxLength = 50000;
        $chunks = [];

        if (strlen($text) > $maxLength) {
            $lines = explode("\n", $text);
            $currentChunk = '';

            foreach ($lines as $line) {
                if (strlen($currentChunk) + strlen($line) > $maxLength) {
                    $chunks[] = $currentChunk;
                    $currentChunk = $line . "\n";
                } else {
                    $currentChunk .= $line . "\n";
                }
            }
            if (!empty($currentChunk)) {
                $chunks[] = $currentChunk;
            }
        } else {
            $chunks[] = $text;
        }

        foreach ($chunks as $index => $chunk) {
            Log::info('Despachando chunk para AiCatalogParser.', [
                'catalog_id' => $catalogId,
                'chunk_index' => $index,
                'chunk_size' => strlen($chunk)
            ]);

            \App\Jobs\ParseAiCatalogChunkJob::dispatch($chunk, $catalogId);
        }

        // Retorna vacío porque el procesamiento se hará de forma asíncrona en el Job
        return [];
    }
}
