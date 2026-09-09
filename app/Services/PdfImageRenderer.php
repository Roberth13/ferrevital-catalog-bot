<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfImageRenderer
{
    public function __construct(
        private readonly string $pdftoppmPath,
    ) {
    }

    /**
     * Renderiza una página del PDF a PNG.
     *
     * @return string Ruta absoluta de la imagen generada.
     */
    public function renderPage(string $pdfPath, int $page, int $dpi = 300): string
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException("PDF no encontrado: {$pdfPath}");
        }

        if ($page < 1) {
            throw new RuntimeException('El número de página debe ser mayor que 0.');
        }

        $outputBase = tempnam(sys_get_temp_dir(), 'pdf_page_');

        if ($outputBase === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }

        // pdftoppm agrega .png automáticamente.
        unlink($outputBase);

        $process = new Process([
            $this->pdftoppmPath,
            '-f',
            (string) $page,
            '-l',
            (string) $page,
            '-singlefile',
            '-png',
            '-r',
            (string) $dpi,
            $pdfPath,
            $outputBase,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Error ejecutando pdftoppm: " . $process->getErrorOutput()
            );
        }

        $imagePath = $outputBase . '.png';

        if (!file_exists($imagePath)) {
            throw new RuntimeException(
                "pdftoppm no generó la imagen esperada: {$imagePath}"
            );
        }

        return $imagePath;
    }
}