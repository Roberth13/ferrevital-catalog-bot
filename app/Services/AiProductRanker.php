<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiProductRanker
{
    /**
     * Evalúa y clasifica los productos según relación calidad-precio usando Gemini.
     *
     * @param Collection $products
     * @param string $searchQuery
     * @return array<int, array{id: int, motivo: string}>
     */
    public function rank(Collection $products, string $searchQuery): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            Log::warning('AiProductRanker: GEMINI_API_KEY no configurada.');
            return $this->fallbackDefault($products);
        }

        // Limitamos a los primeros 20 productos para rapidez y bajo consumo de tokens
        $subset = $products->take(20);

        $items = $subset->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'codigo' => (string) $p->codigo,
                'nombre' => (string) $p->nombre,
                'precio_costo_usd' => (float) ($p->precio_divisa ?? 0),
                'precio_venta_usd' => (float) ($p->precio_venta_usd ?? 0),
                'garantia' => (string) ($p->garantia ?: ($p->supplier?->garantia_default ?: 'No especificada')),
                'descripcion_tecnica' => (string) ($p->descripcion_tecnica ?: ''),
                'incluye' => (string) ($p->incluye ?: ''),
            ];
        })->values()->toArray();

        $prompt = "Eres un experto asesor técnico y de compras de herramientas y ferretería.
El cliente busca: '{$searchQuery}'.
Analiza los siguientes productos del inventario y determina cuál es la MEJOR OPCIÓN en relación CALIDAD-PRECIO (Value for Money) para esa búsqueda.
Criterios de evaluación:
- Relación de potencia, capacidad, motor (ej. Brushless vs carbones), voltaje o uso profesional frente al precio (usa precio_venta_usd si existe, o precio_costo_usd).
- Tiempo y respaldo de garantía.
- Accesorios o kits incluidos (maletín, baterías, etc.).
- Penaliza precios excesivamente altos sin justificación técnica y favorece la mayor robustez al menor costo razonable.

Devuelve ESTRICTAMENTE un JSON con la propiedad 'ranking', que sea un array ordenado de objetos donde el índice 0 sea la MEJOR opción indiscutible. Cada objeto debe tener 'id' (el número entero del producto) y 'motivo' (una explicación breve y concisa de 1 o 2 líneas de por qué es conveniente para el comprador).";

        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt . "\n\nPRODUCTOS DISPONIBLES:\n" . json_encode($items, JSON_UNESCAPED_UNICODE)]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'ranking' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'id' => ['type' => 'INTEGER'],
                                    'motivo' => ['type' => 'STRING'],
                                ],
                                'required' => ['id', 'motivo']
                            ]
                        ]
                    ],
                    'required' => ['ranking']
                ]
            ]
        ];

        try {
            $response = Http::timeout(25)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );

            if ($response->successful()) {
                $body = $response->json();
                $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode($rawText, true);

                if (isset($decoded['ranking']) && is_array($decoded['ranking'])) {
                    return $decoded['ranking'];
                }
            } else {
                Log::warning('AiProductRanker: Fallo en llamada a Gemini.', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300)
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('AiProductRanker: Excepción al consultar Gemini: ' . $e->getMessage());
        }

        return $this->fallbackDefault($products);
    }

    private function fallbackDefault(Collection $products): array
    {
        return $products->map(fn($p) => [
            'id' => $p->id,
            'motivo' => 'Orden predeterminado del catálogo.'
        ])->toArray();
    }
}

