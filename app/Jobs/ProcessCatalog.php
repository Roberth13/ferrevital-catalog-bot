<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessCatalog implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public Catalog $catalog)
    {
    }

    public function handle(): void
    {
        $this->catalog->update([
            'status' => 'procesando',
        ]);

        $fullPdfPath = Storage::disk('local')->path($this->catalog->path);
        if (! file_exists($fullPdfPath)) {
            $this->catalog->update([
                'status' => 'error',
                'message' => "El archivo PDF no existe en la ruta: {$fullPdfPath}",
            ]);
            return;
        }

        $tempOutFile = tempnam(sys_get_temp_dir(), 'cat_out_') . '.json';

        $pythonBin = config('services.python.bin', 'python');
        $scriptPath = base_path('python/extract.py');

        $command = [
            $pythonBin,
            $scriptPath,
            '--pdf',
            $fullPdfPath,
            '--out',
            $tempOutFile,
        ];

        if (! is_null($this->catalog->first_page)) {
            $command[] = '--first';
            $command[] = (string) $this->catalog->first_page;
        }

        if (! is_null($this->catalog->last_page)) {
            $command[] = '--last';
            $command[] = (string) $this->catalog->last_page;
        }

        $env = [
            'GEMINI_API_KEY' => config('services.gemini.api_key'),
            'GEMINI_MODEL' => config('services.gemini.model', 'gemini-3.5-flash-lite'),
            'GEMINI_PRICE_IN' => (string) config('services.gemini.price_in', 0.30),
            'GEMINI_PRICE_OUT' => (string) config('services.gemini.price_out', 2.50),
        ];

        $process = new Process($command, base_path(), $env, null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = $process->getErrorOutput();
            $truncatedErr = substr($stderr, -2000);
            if (empty($truncatedErr)) {
                $truncatedErr = substr($process->getOutput(), -2000);
            }

            $this->catalog->update([
                'status' => 'error',
                'message' => $truncatedErr ?: 'Error desconocido al ejecutar el script de extracción.',
            ]);

            if (file_exists($tempOutFile)) {
                @unlink($tempOutFile);
            }
            return;
        }

        if (! file_exists($tempOutFile)) {
            $this->catalog->update([
                'status' => 'error',
                'message' => 'El script de extracción no generó el archivo JSON de salida.',
            ]);
            return;
        }

        $rawJson = file_get_contents($tempOutFile);
        $data = json_decode($rawJson, true);

        if (! is_array($data) || ! isset($data['productos'])) {
            $this->catalog->update([
                'status' => 'error',
                'message' => 'El JSON producido por el script de extracción es inválido.',
            ]);
            @unlink($tempOutFile);
            return;
        }

        $productos = $data['productos'];
        $totalPages = $data['paginas'] ?? null;
        $costUsd = $data['costo_usd'] ?? null;
        $advertencias = $data['advertencias'] ?? [];
        $supplierId = $this->catalog->supplier_id;

        DB::transaction(function () use ($productos, $supplierId, $totalPages, $costUsd, $advertencias) {
            foreach ($productos as $prod) {
                $codigo = (string) ($prod['codigo'] ?? '');
                $variante = (string) ($prod['variante'] ?? '');
                $revisarList = $prod['revisar'] ?? [];
                $esRevisar = ! empty($revisarList);
                $motivosStr = $esRevisar ? implode('; ', $revisarList) : null;

                Product::updateOrCreate(
                    [
                        'supplier_id' => $supplierId,
                        'codigo' => $codigo,
                        'variante' => $variante,
                    ],
                    [
                        'catalog_id' => $this->catalog->id,
                        'nombre' => $prod['nombre'] ?? '',
                        'precio_divisa' => $prod['precio_divisa'] ?? null,
                        'precio_bs' => $prod['precio_bs'] ?? null,
                        'otros_precios' => $prod['otros_precios'] ?? null,
                        'descripcion_tecnica' => $prod['descripcion_tecnica'] ?? null,
                        'incluye' => $prod['incluye'] ?? null,
                        'garantia' => $prod['garantia'] ?? null,
                        'pagina' => $prod['pagina'] ?? null,
                        'revisar' => $esRevisar,
                        'revisar_motivo' => $motivosStr,
                    ]
                );
            }

            $message = ! empty($advertencias) ? implode("\n", $advertencias) : null;

            $this->catalog->update([
                'status' => 'listo',
                'total_pages' => $totalPages,
                'total_products' => count($productos),
                'cost_usd' => $costUsd,
                'message' => $message,
            ]);
        });

        @unlink($tempOutFile);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Fallo en job ProcessCatalog: ' . ($exception ? $exception->getMessage() : ''));
        $this->catalog->update([
            'status' => 'error',
            'message' => $exception ? substr($exception->getMessage(), 0, 2000) : 'Job fallido.',
        ]);
    }
}

