<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\PdfImageRenderer::class, function ($app) {
            return new \App\Services\PdfImageRenderer(
                config('services.pdf.pdftoppm_path')
            );
        });

        $this->app->singleton(\App\Services\OcrTextExtractor::class, function ($app) {
            return new \App\Services\OcrTextExtractor(
                config('services.ocr.tesseract_path')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
