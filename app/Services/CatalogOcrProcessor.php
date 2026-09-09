<?php

namespace App\Services;

class CatalogOcrProcessor
{
    public function __construct(
        private readonly PdfImageRenderer $renderer,
        private readonly OcrTextExtractor $ocr,
        private readonly ColorTextExtractor $colorExtractor,
    ) {
    }

    public function processPage(
        string $pdfPath,
        int $page,
        int $dpi = 300,
    ): array {
        $imagePath = $this->renderer->renderPage(
            $pdfPath,
            $page,
            $dpi
        );

        try {
            $normalText = $this->ocr->extract(
                $imagePath,
                'spa+eng',
                6
            );

            $redImagePath = $this->colorExtractor->extractRed(
                $imagePath
            );

            try {
                $redText = $this->ocr->extract(
                    $redImagePath,
                    'spa+eng',
                    11
                );
            } finally {
                $this->deleteTemporaryFile($redImagePath);
            }

            return [
                'page' => $page,
                'normal_text' => $normalText,
                'red_text' => $redText,
            ];
        } finally {
            $this->deleteTemporaryFile($imagePath);
        }
    }

    private function deleteTemporaryFile(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function process(
        string $pdfPath,
        int $totalPages
    ): array {
        $products = [];

        for ($page = 1; $page <= $totalPages; $page++) {
            try {
                $result = $this->processPage($pdfPath, $page, 150);

                $normalText = trim($result['normal_text']);
                $redText = trim($result['red_text']);

                // Página sin contenido útil.
                if ($normalText === '' && $redText === '') {
                    continue;
                }

                $products[] = [
                    'page' => $page,
                    'normal_text' => $normalText,
                    'red_text' => $redText,
                ];
            } catch (\Throwable $e) {
                report($e);

                $products[] = [
                    'page' => $page,
                    'normal_text' => '',
                    'red_text' => '',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $products;
    }
}