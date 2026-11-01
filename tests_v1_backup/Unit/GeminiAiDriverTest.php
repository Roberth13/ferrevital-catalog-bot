<?php

namespace Tests\Unit;

use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;
use App\Exceptions\Ai\AiTemporaryException;
use App\Services\Ai\Drivers\GeminiAiDriver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeminiAiDriverTest extends TestCase
{
    private string $testApiKey = 'test-gemini-key-12345';
    private string $testModel = 'gemini-3.6-flash';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.gemini.api_key' => $this->testApiKey]);
        config(['services.ai.gemini.model' => $this->testModel]);
        config(['services.ai.extraction_prompt_version' => 'v1']);
    }

    /**
     * 1-5. Validar request correcto: endpoint, modelo, prompt, JSON Schema y temperature 0.0
     */
    public function test_extract_products_sends_correct_payload_structure_and_headers(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    [
                                        'codigo' => 'SKU-001',
                                        'nombre' => 'Taladro Percutor 20V',
                                        'precio_bs' => 4500.00,
                                        'precio_divisa' => 120.00,
                                        'descripcion' => 'Motor sin escobillas',
                                        'garantia' => '1 año',
                                        'condiciones' => 'Venta unitaria',
                                        'tiempo_entrega' => 'Inmediata'
                                    ]
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);
        $result = $driver->extractProductsFromChunk("Texto de catálogo de prueba");

        // 6-7. Validar conversión correcta de respuesta con los 8 campos esperados
        $this->assertCount(1, $result);
        $product = $result[0];
        $this->assertEquals('SKU-001', $product['codigo']);
        $this->assertEquals('Taladro Percutor 20V', $product['nombre']);
        $this->assertEquals(4500.00, $product['precio_bs']);
        $this->assertEquals(120.00, $product['precio_divisa']);
        $this->assertEquals('Motor sin escobillas', $product['descripcion']);
        $this->assertEquals('1 año', $product['garantia']);
        $this->assertEquals('Venta unitaria', $product['condiciones']);
        $this->assertEquals('Inmediata', $product['tiempo_entrega']);

        // Validar llamada HTTP interceptada
        Http::assertSent(function ($request) {
            $url = $request->url();
            $data = $request->data();

            $hasCorrectUrl = str_contains($url, 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->testModel . ':generateContent')
                && str_contains($url, 'key=' . $this->testApiKey);

            $hasTemperatureZero = isset($data['generationConfig']['temperature']) && $data['generationConfig']['temperature'] === 0.0;
            $hasJsonMimeType = isset($data['generationConfig']['responseMimeType']) && $data['generationConfig']['responseMimeType'] === 'application/json';
            $hasResponseSchema = isset($data['generationConfig']['responseSchema']['type']) && $data['generationConfig']['responseSchema']['type'] === 'ARRAY';
            $hasRequiredFields = isset($data['generationConfig']['responseSchema']['items']['required'])
                && in_array('codigo', $data['generationConfig']['responseSchema']['items']['required'])
                && in_array('nombre', $data['generationConfig']['responseSchema']['items']['required'])
                && in_array('precio_divisa', $data['generationConfig']['responseSchema']['items']['required']);

            $hasPrompt = isset($data['contents'][0]['parts'][0]['text'])
                && str_contains($data['contents'][0]['parts'][0]['text'], 'Eres un asistente experto en catálogos de ferretería');

            return $hasCorrectUrl && $hasTemperatureZero && $hasJsonMimeType && $hasResponseSchema && $hasRequiredFields && $hasPrompt;
        });
    }

    /**
     * 8. Respuesta vacía de candidatos -> retorna array vacío
     */
    public function test_extract_products_handles_empty_json_array_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '[]']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);
        $result = $driver->extractProductsFromChunk("Texto sin productos");

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * 9. JSON malformado en parts.text -> lanza AiResponseParseException
     */
    public function test_extract_products_throws_parse_exception_on_malformed_json_in_parts(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{ malformed json unclosed']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiResponseParseException::class);
        $driver->extractProductsFromChunk("Texto");
    }

    /**
     * 9b. JSON no estructurado en HTTP response body -> lanza AiResponseParseException
     */
    public function test_extract_products_throws_parse_exception_on_raw_non_json_body(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('NOT JSON AT ALL', 200)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiResponseParseException::class);
        $driver->extractProductsFromChunk("Texto");
    }

    /**
     * 10. HTTP 429 Rate Limit -> lanza AiRateLimitException
     */
    public function test_extract_products_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiRateLimitException::class);
        $driver->extractProductsFromChunk("Texto");
    }

    /**
     * 11. HTTP 500/502/503 -> lanza AiTemporaryException
     */
    public function test_extract_products_throws_temporary_exception_on_500(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Internal Server Error'], 500)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiTemporaryException::class);
        $this->expectExceptionCode(500);
        $driver->extractProductsFromChunk("Texto");
    }

    public function test_extract_products_throws_temporary_exception_on_503(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Service Unavailable'], 503)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiTemporaryException::class);
        $this->expectExceptionCode(503);
        $driver->extractProductsFromChunk("Texto");
    }

    /**
     * 12. Timeout / Network exception -> lanza AiTemporaryException
     */
    public function test_extract_products_throws_temporary_exception_on_network_connection_failure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => function () {
                throw new ConnectionException("cURL error 28: Operation timed out");
            }
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        $this->expectException(AiTemporaryException::class);
        $this->expectExceptionMessageMatches('/Error de red con Gemini/i');
        $driver->extractProductsFromChunk("Texto");
    }

    /**
     * Validación de seguridad: Enmascaramiento de API Key en logs y excepciones
     */
    public function test_api_key_is_masked_in_logs_and_exceptions(): void
    {
        Log::spy();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => "key={$this->testApiKey} leaked"], 500)
        ]);

        $driver = new GeminiAiDriver($this->testApiKey, $this->testModel);

        try {
            $driver->extractProductsFromChunk('texto');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString($this->testApiKey, $e->getMessage());
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(function ($message, $context) {
                return !str_contains($context['body'] ?? '', $this->testApiKey);
            });
    }

    /**
     * Metadatos del proveedor
     */
    public function test_getters_return_configured_provider_metadata(): void
    {
        $driver = new GeminiAiDriver('key', 'gemini-3.6-flash');
        $this->assertEquals('gemini', $driver->getProviderName());
        $this->assertEquals('gemini-3.6-flash', $driver->getModelName());
        $this->assertEquals('v1', $driver->getPromptVersion());
    }
}
