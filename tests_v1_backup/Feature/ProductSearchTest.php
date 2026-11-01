<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $supplier = Supplier::create(['name' => 'Ferretería Central', 'slug' => 'ferreteria-central']);
        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalog_test.pdf',
            'original_filename' => 'catalog_test.pdf',
            'status' => 'completed',
        ]);

        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'TAL-100',
            'nombre' => 'Taladro Percutor 1/2',
            'descripcion' => 'Taladro profesional de alta potencia 800W',
            'precio_divisa' => 45.00,
            'is_active' => true,
        ]);

        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'TAL-200',
            'nombre' => 'Taladro Inalámbrico 20V',
            'descripcion' => 'Taladro a batería 2.0Ah',
            'precio_divisa' => 85.00,
            'is_active' => true,
        ]);

        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'AMAR-50',
            'nombre' => 'Amoladora Angular 4-1/2',
            'descripcion' => 'Herramienta para corte de metal',
            'precio_divisa' => 35.00,
            'is_active' => true,
        ]);
    }

    public function test_get_products_index_works_without_ai()
    {
        Http::fake();

        $response = $this->get('/products');

        $response->assertStatus(200);
        $response->assertViewHas('products');
        Http::assertNothingSent();
    }

    public function test_get_products_with_search_query_works_without_ai()
    {
        Http::fake();

        $response = $this->get('/products?search=taladro');

        $response->assertStatus(200);
        $response->assertSee('Taladro Percutor 1/2');
        $response->assertSee('Taladro Inalámbrico 20V');
        $response->assertDontSee('Amoladora Angular 4-1/2');

        Http::assertNothingSent();
    }

    public function test_search_works_even_if_gemini_api_is_down_or_throws_503()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $response = $this->get('/products?search=taladro');

        $response->assertStatus(200);
        $response->assertSee('Taladro Percutor 1/2');
        Http::assertNothingSent();
    }

    public function test_search_works_even_if_gemini_api_returns_429_rate_limit()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Too Many Requests'], 429),
        ]);

        $response = $this->get('/products?search=TAL-100');

        $response->assertStatus(200);
        $response->assertSee('TAL-100');
        Http::assertNothingSent();
    }

    public function test_search_does_not_wait_for_ai_api_delays()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => function () {
                sleep(2); // Retraso simulado de API externa
                return Http::response([], 200);
            },
        ]);

        $start = microtime(true);
        $response = $this->get('/products?search=AMAR-50');
        $duration = microtime(true) - $start;

        $response->assertStatus(200);
        $response->assertSee('Amoladora Angular 4-1/2');
        $this->assertLessThan(1.0, $duration, 'La respuesta de búsqueda HTTP tardó más de 1 segundo');
        Http::assertNothingSent();
    }

    public function test_deterministic_ranking_orders_exact_code_match_first()
    {
        $response = $this->get('/products?search=TAL-100');

        $response->assertStatus(200);
        $products = $response->viewData('products');

        $this->assertEquals('TAL-100', $products->first()->codigo);
    }
}
