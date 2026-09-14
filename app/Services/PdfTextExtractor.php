<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        if (!file_exists($path)) {
            throw new RuntimeException(
                "El archivo PDF no existe: {$path}"
            );
        }

        try {
            $isWindows = PHP_OS_FAMILY === 'Windows';
            $binPath = config('services.poppler.bin_path');
            $binaryName = $isWindows ? 'pdftotext.exe' : 'pdftotext';

            if (!empty($binPath)) {
                $executable = rtrim($binPath, '/\\') . DIRECTORY_SEPARATOR . $binaryName;
            } else {
                $executable = $isWindows ? 'C:\\Tools\\poppler\\Library\\bin\\pdftotext.exe' : 'pdftotext';
            }

            $process = new Process([
                $executable,
                $path,
                '-' // Imprimir stdout
            ]);
            
            // Timeout de 10 min por si el pdf es GIGANTE
            $process->setTimeout(600);
            $process->run();

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }

            throw new RuntimeException("Error en pdftotext: " . $process->getErrorOutput());

        } catch (\Throwable $e) {
            throw new RuntimeException(
                "No se pudo extraer el texto del PDF: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}