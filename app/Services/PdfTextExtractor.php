<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function __construct(
        private readonly Parser $parser
    ) {
    }

    public function extract(string $path): string
    {
        if (!file_exists($path)) {
            throw new RuntimeException(
                "El archivo PDF no existe: {$path}"
            );
        }

        try {
            if (stripos($path, 'RONIX') !== false) {
                $process = new \Symfony\Component\Process\Process([
                    'C:\\Tools\\poppler\\Library\\bin\\pdftotext.exe',
                    $path,
                    '-'
                ]);
                $process->run();
                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
            }

            // Aumentamos memoria por si acaso
            ini_set('memory_limit', '1024M');
            $pdf = $this->parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "No se pudo extraer el texto del PDF: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}