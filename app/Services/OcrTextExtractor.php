<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class OcrTextExtractor
{
    public function __construct(
        private readonly string $tesseractPath,
    ) {
    }

    public function extract(
        string $imagePath,
        string $language = 'spa+eng',
        int $psm = 6,
    ): string {
        if (!file_exists($imagePath)) {
            throw new RuntimeException("Imagen no encontrada: {$imagePath}");
        }

        $process = new Process([
            $this->tesseractPath,
            $imagePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            (string) $psm,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Error ejecutando Tesseract: " . $process->getErrorOutput()
            );
        }

        return trim($process->getOutput());
    }
}