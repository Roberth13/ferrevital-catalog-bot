<?php

namespace App\Services\Parsers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiCatalogParser implements CatalogParserInterface
{
    /**
     * Divide el texto en chunks y genera los Jobs correspondientes sin despacharlos.
     *
     * @return array<int, \App\Jobs\ParseAiCatalogChunkJob>
     */
    public function createJobs(string $normalText, string $redText = '', ?int $catalogId = null, ?int $pageNumber = null): array
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

        $jobs = [];
        foreach ($chunks as $index => $chunk) {
            Log::info('Preparando chunk para AiCatalogParser.', [
                'catalog_id' => $catalogId,
                'page_number' => $pageNumber,
                'chunk_index' => $index,
                'chunk_size' => strlen($chunk)
            ]);

            $jobs[] = new \App\Jobs\ParseAiCatalogChunkJob($chunk, $catalogId, $pageNumber);
        }

        return $jobs;
    }

    public function parse(string $normalText, string $redText = '', ?int $catalogId = null, ?int $pageNumber = null): array
    {
        $jobs = $this->createJobs($normalText, $redText, $catalogId, $pageNumber);

        if (empty($jobs)) {
            return [];
        }

        if ($catalogId) {
            \Illuminate\Support\Facades\Bus::batch($jobs)
                ->name('catalog-' . $catalogId)
                ->then(function (\Illuminate\Bus\Batch $batch) use ($catalogId) {
                    $total = \Illuminate\Support\Facades\DB::table('products')->where('catalog_id', $catalogId)->count();
                    \App\Models\Catalog::where('id', $catalogId)->update([
                        'status' => 'completed',
                        'total_products' => $total,
                    ]);
                })
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($catalogId) {
                    \App\Models\Catalog::where('id', $catalogId)->update([
                        'status' => 'failed',
                    ]);
                })
                ->dispatch();
        } else {
            foreach ($jobs as $job) {
                dispatch($job);
            }
        }

        return [];
    }
}
