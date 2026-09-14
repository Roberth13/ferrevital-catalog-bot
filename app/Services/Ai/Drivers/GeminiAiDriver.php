<?php

namespace App\Services\Ai\Drivers;

use App\Contracts\AiProviderInterface;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;
use App\Exceptions\Ai\AiTemporaryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiAiDriver implements AiProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.ai.gemini.api_key') ?? config('services.gemini.api_key', '');
        $this->model = $model ?? config('services.ai.gemini.model') ?? config('services.gemini.model', 'gemini-3.6-flash');
    }

    public function extractProductsFromChunk(string $chunkText): array
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key no configurada.');
            throw new AiException('Gemini API Key no configurada.');
        }

        $prompt = "Eres un asistente experto en catálogos de ferretería.
Debes extraer los productos del siguiente texto, que provienen de una página de un PDF.
Devuelve los resultados strictly en formato JSON de acuerdo al responseSchema definido.

Reglas:
1. codigo: Si no hay código (SKU), usa vacio o ignora.
2. nombre: Nombre completo del producto.
3. precio_divisa: Extrae como número (solo la cifra, sin $). Si no hay, usa 0.
4. precio_bs: Extrae como número (solo la cifra, sin Bs). Si no hay, usa 0.
5. descripcion: Texto adicional si aplica.
6. garantia: Si hay texto sobre garantía en la página (ej: 'garantía de 1 año'), aplícala a los productos, sino vacio.
7. condiciones: Condiciones de proveedor (ej: 'Venta por bulto cerrado'), sino vacio.
8. tiempo_entrega: Tiempo de entrega si se menciona, sino vacio.

Ignora texto legal, basura OCR o encabezados que no sean productos.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt . "\n\nTEXTO A ANALIZAR:\n" . $chunkText]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'codigo' => ['type' => 'STRING'],
                            'nombre' => ['type' => 'STRING'],
                            'precio_divisa' => ['type' => 'NUMBER'],
                            'precio_bs' => ['type' => 'NUMBER'],
                            'descripcion' => ['type' => 'STRING'],
                            'garantia' => ['type' => 'STRING'],
                            'condiciones' => ['type' => 'STRING'],
                            'tiempo_entrega' => ['type' => 'STRING']
                        ],
                        'required' => [
                            'codigo', 'nombre', 'precio_divisa', 'precio_bs',
                            'descripcion', 'garantia', 'condiciones', 'tiempo_entrega'
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(120)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey,
                $payload
            );
        } catch (Exception $e) {
            $sanitizedMessage = $this->sanitizeLogMessage($e->getMessage());
            Log::warning('Error de red al conectar con Gemini.', ['error' => $sanitizedMessage]);
            throw new AiTemporaryException("Error de red con Gemini: " . $sanitizedMessage, 0, $e);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $sanitizedBody = $this->sanitizeLogMessage(substr($response->body(), 0, 500));

            Log::warning("Respuesta fallida de Gemini", ['status' => $status, 'body' => $sanitizedBody]);

            if ($status === 429) {
                throw new AiRateLimitException("Gemini límite de cuotas excedido (HTTP 429).", $status);
            }

            if ($status >= 500 && $status <= 599) {
                throw new AiTemporaryException("Gemini servicio no disponible temporalmente (HTTP {$status}).", $status);
            }

            throw new AiException("Error de cliente Gemini (HTTP {$status})");
        }

        $data = $response->json();

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $rawBody = $this->sanitizeLogMessage(substr($response->body(), 0, 1000));
            throw new AiResponseParseException("Respuesta JSON inválida de Gemini.", $rawBody);
        }

        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
        $products = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($products)) {
            $rawPayload = $this->sanitizeLogMessage(substr($jsonStr, 0, 1000));
            throw new AiResponseParseException("El contenido extraído por Gemini no es un JSON válido.", $rawPayload);
        }

        return $products;
    }

    public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array
    {
        if (empty($productsData)) {
            return [];
        }

        if (count($productsData) === 1) {
            return [$productsData[0]['id'] ?? null];
        }

        if (empty($this->apiKey)) {
            Log::warning("No se pudo rankear productos con Gemini: API Key no configurada.");
            return array_column($productsData, 'id');
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
                        ['text' => $prompt . "\n\nPRODUCTOS:\n" . json_encode($productsData, JSON_UNESCAPED_UNICODE)]
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
            $response = Http::timeout(15)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey,
                $apiPayload
            );

            if ($response->successful()) {
                $data = $response->json();
                $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                $rankedIds = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($rankedIds)) {
                    $originalIds = array_column($productsData, 'id');
                    $validIds = array_values(array_intersect($rankedIds, $originalIds));
                    $missingIds = array_values(array_diff($originalIds, $validIds));

                    return array_merge($validIds, $missingIds);
                }
            } else {
                $sanitizedBody = $this->sanitizeLogMessage(substr($response->body(), 0, 500));
                Log::warning("Falló el rankeo de AI Gemini", ['status' => $response->status(), 'body' => $sanitizedBody]);
            }
        } catch (Exception $e) {
            Log::error("Excepción en rankeo de AI Gemini: " . $this->sanitizeLogMessage($e->getMessage()));
        }

        return array_column($productsData, 'id');
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    public function getPromptVersion(): string
    {
        return config('services.ai.extraction_prompt_version', 'v1');
    }

    /**
     * Oculta cualquier aparición de la API Key en cadenas o mensajes para evitar exposiciones en logs.
     */
    private function sanitizeLogMessage(string $message): string
    {
        if (empty($this->apiKey)) {
            return $message;
        }

        return str_replace($this->apiKey, '***MASKED_KEY***', $message);
    }
}
