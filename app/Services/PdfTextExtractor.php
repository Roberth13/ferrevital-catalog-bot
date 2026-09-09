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
            $binPath = config('services.poppler.bin_path', 'C:\\Tools\\poppler\\Library\\bin');
            $executable = $binPath . '\\pdftotext.exe';
            
            // Reemplazo de slashes para asegurar formato correcto en Windows si es necesario
            $executable = str_replace('/', '\\', $executable);

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