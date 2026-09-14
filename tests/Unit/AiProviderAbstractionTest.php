<?php

namespace Tests\Unit;

use App\Contracts\AiProviderInterface;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Services\AiProductRanker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderAbstractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_job_uses_ai_provider_interface_contract()
    {
        Http::fake();

        $fakeProvider = new class implements AiProviderInterface {
            public function extractProductsFromChunk(string $chunkText): array {
                return [
                    [
                        'codigo' => 'PRO-99',
                        'nombre' => 'Producto Fake AI',
                        'precio_divisa' => 99.99,
                        'precio_bs' => 0,
                        'descripcion' => 'Descripción Fake',
                        'garantia' => '1 año',
                        'condiciones' => '',
                        'tiempo_entrega' => ''
                    ]
                ];
            }

            public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array {
                return [99];
            }

            public function getProviderName(): string { return 'fake-ai'; }
            public function getModelName(): string { return 'fake-model'; }
            public function getPromptVersion(): string { return 'v1'; }
        };

        $this->app->instance(AiProviderInterface::class, $fakeProvider);

        $catalog = Catalog::create([
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'status' => 'processing',
        ]);

        $job = new ParseAiCatalogChunkJob("chunk de prueba", $catalog->id);
        $job->handle($fakeProvider);

        $this->assertDatabaseHas('products', [
            'codigo' => 'PRO-99',
            'nombre' => 'Producto Fake AI',
            'catalog_id' => $catalog->id,
        ]);

        // Garantizar que no se enviaron peticiones HTTP externas a Gemini
        Http::assertNothingSent();
    }

    public function test_ai_product_ranker_uses_ai_provider_interface_contract()
    {
        Http::fake();

        $fakeProvider = new class implements AiProviderInterface {
            public function extractProductsFromChunk(string $chunkText): array {
                return [];
            }

            public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array {
                return [2, 1]; // Invertir orden
            }

            public function getProviderName(): string { return 'fake-ai'; }
            public function getModelName(): string { return 'fake-model'; }
            public function getPromptVersion(): string { return 'v1'; }
        };

        $products = collect([
            new Product(['id' => 1, 'nombre' => 'Producto 1', 'precio_divisa' => 100]),
            new Product(['id' => 2, 'nombre' => 'Producto 2', 'precio_divisa' => 200]),
        ]);

        $ranker = new AiProductRanker($fakeProvider);
        $result = $ranker->rankByValueForMoney($products, 'prueba');

        $this->assertEquals([2, 1], $result);
        Http::assertNothingSent();
    }
}
