<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AiProductRanker
{
    public function __construct(
        private readonly ?AiProviderInterface $aiProvider = null
    ) {
    }

    /**
     * Recibe una colección de productos y devuelve un array de IDs ordenados por la mejor calidad-precio.
     *
     * @param Collection $products
     * @param string $searchQuery
     * @return array
     */
    public function rankByValueForMoney(Collection $products, string $searchQuery): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        if ($products->count() === 1) {
            return [$products->first()->id];
        }

        $payloadData = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio_divisa' => (float) $p->precio_divisa,
                'descripcion' => $p->descripcion,
                'garantia' => $p->garantia,
            ];
        })->values()->toArray();

        try {
            $provider = $this->aiProvider ?? app(AiProviderInterface::class);
            return $provider->rankProductsByValueForMoney($payloadData, $searchQuery);
        } catch (\Throwable $e) {
            Log::error("Excepción en AiProductRanker: " . $e->getMessage());
            return $products->pluck('id')->toArray();
        }
    }
}
