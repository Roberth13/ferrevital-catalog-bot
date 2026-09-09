<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Services\CatalogProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout amplio para OCR o catálogos grandes (20 minutos)
    public $timeout = 1200;

    public function __construct(
        public readonly Catalog $catalog
    ) {
    }

    public function handle(CatalogProcessor $processor): void
    {
        $processor->process($this->catalog);
    }
}
