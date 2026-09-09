<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiProductRanker
{
    /**
     * Recibe una colección de productos y devuelve un array de IDs ordenados
     * por la mejor calidad-precio.
     *
     * @param Collection $products
     * @param string $searchQuery La consulta que hizo el usuario (para dar contexto a la IA)
     * @return array
     */
    public function rankByValueForMoney(Collection $products, string $searchQuery): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        // Si solo hay un producto, no hace falta ordenar
        if ($products->count() === 1) {
            return [$products->first()->id];
        }

        // Preparamos los datos para enviar (minimizando tokens)
        $payloadData = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio_divisa' => (float) $p->precio_divisa,
                'descripcion' => $p->descripcion,
                'garantia' => $p->garantia,
            ];
        })->values()->toArray();

        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::warning("No se pudo rankear productos: API Key no configurada.");
            return $products->pluck('id')->toArray(); // Orden original
        }

        $prompt = "Eres un experto asesor de compras de herramientas y ferretería.
El usuario buscó: '{$searchQuery}'.
Te daré una lista de productos en formato JSON que coinciden con su búsqueda.
Tu objetivo es analizar la relación CALIDAD-PRECIO ('Value for Money') de cada uno, basándote en:
- Especificaciones técnicas que sugieran robustez, potencia o uso profesional (en 'descripcion' y 'nombre').
- Tiempo de garantía ('garantia').
- El precio ('precio_divisa'). A menor precio con buenas specs, mejor puntaje.

Devuelve ESTRICTAMENTE un array de JSON de enteros, que corresponda a los 'id' de los productos, ordenados desde LA MEJOR OPCIÓN (índice 0) hasta LA PEOR OPCIÓN. 
Ejemplo de salida válida: [45, 12, 8, 3]
NO incluyas ninguna explicación, texto, ni markdown fuera del array de JSON.";

        $apiPayload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt . "\n\nPRODUCTOS:\n" . json_encode($payloadData, JSON_UNESCAPED_UNICODE)]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'INTEGER'
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(15)->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey, $apiPayload);
            
            if ($response->successful()) {
                $data = $response->json();
                $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                $rankedIds = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($rankedIds)) {
                    // Verificamos que todos los IDs devueltos existan en nuestra colección original
                    $validIds = collect($rankedIds)->intersect($products->pluck('id'))->values()->toArray();
                    
                    // Si la IA omitió algunos, los agregamos al final para no perder data
                    $missingIds = $products->pluck('id')->diff($validIds)->values()->toArray();
                    
                    return array_merge($validIds, $missingIds);
                }
            } else {
                Log::warning("Falló el rankeo de AI", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Excepción en rankeo de AI: " . $e->getMessage());
        }

        // Fallback: Devolvemos el orden original si algo falla
        return $products->pluck('id')->toArray();
    }
}
