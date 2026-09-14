<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\SalePrice\SalePriceCalculatorService;
use App\Services\SalePrice\SalePriceFormulaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePriceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;
    protected Catalog $catalog;
    protected SalePriceCalculatorService $calculatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Proveedor Test',
            'slug' => 'proveedor-test',
        ]);

        $this->catalog = Catalog::create([
            'supplier_id' => $this->supplier->id,
            'filename' => 'catalogs/test.pdf',
            'original_filename' => 'test.pdf',
            'status' => 'completed',
        ]);

        $this->calculatorService = new SalePriceCalculatorService(new SalePriceFormulaRegistry());
    }

    public function test_original_supplier_prices_remain_strictly_immutable_on_divisa_calculation(): void
    {
        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'PROD-001',
            'nombre' => 'Taladro Percutor 710W',
            'precio_divisa' => 7.84,
            'precio_bs' => 280.00,
            'is_active' => true,
        ]);

        // Aplicar Markup del 25% sobre DIVISA: 7.84 * 1.25 = 9.80
        $result = $this->calculatorService->apply(
            [$product->id],
            'percentage_markup',
            'divisa',
            ['percentage' => 25]
        );

        $this->assertEquals(1, $result['updated']);

        $product->refresh();

        // 1. PRECIO ORIGINAL DE PROVEEDOR INMUTABLE
        $this->assertEquals(7.84, (float) $product->precio_divisa, 'El precio_divisa original del proveedor fue modificado');
        $this->assertEquals(280.00, (float) $product->precio_bs, 'El precio_bs original del proveedor fue modificado');

        // 2. NUEVO PRECIO DE VENTA Y TRAZABILIDAD
        $this->assertEquals(9.80, (float) $product->precio_venta_divisa);
        $this->assertNull($product->precio_venta_bs);
        $this->assertEquals('percentage_markup', $product->sale_price_formula);
        $this->assertEquals('divisa', $product->sale_price_base);
        $this->assertNotNull($product->sale_price_applied_at);
    }

    public function test_original_supplier_prices_remain_strictly_immutable_on_bs_calculation(): void
    {
        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'PROD-002',
            'nombre' => 'Amoladora Angular 115mm',
            'precio_divisa' => 45.00,
            'precio_bs' => 1500.00,
            'is_active' => true,
        ]);

        // Aplicar Margen Comercial del 20% sobre BS: 1500 / (1 - 0.20) = 1875.00
        $result = $this->calculatorService->apply(
            [$product->id],
            'percentage_margin',
            'bs',
            ['margin' => 20]
        );

        $this->assertEquals(1, $result['updated']);

        $product->refresh();

        // 1. PRECIOS ORIGINALES DE PROVEEDOR INMUTABLES
        $this->assertEquals(45.00, (float) $product->precio_divisa);
        $this->assertEquals(1500.00, (float) $product->precio_bs);

        // 2. NUEVO PRECIO DE VENTA EN BS Y TRAZABILIDAD
        $this->assertEquals(1875.00, (float) $product->precio_venta_bs);
        $this->assertNull($product->precio_venta_divisa);
        $this->assertEquals('percentage_margin', $product->sale_price_formula);
        $this->assertEquals('bs', $product->sale_price_base);
        $this->assertNotNull($product->sale_price_applied_at);
    }

    public function test_skips_products_missing_selected_base_cost_cleanly(): void
    {
        // Producto A tiene divisa pero NO tiene bs
        $p1 = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'SKU-A',
            'nombre' => 'Item A',
            'precio_divisa' => 10.00,
            'precio_bs' => null,
            'is_active' => true,
        ]);

        // Producto B tiene bs pero NO tiene divisa
        $p2 = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'SKU-B',
            'nombre' => 'Item B',
            'precio_divisa' => null,
            'precio_bs' => 350.00,
            'is_active' => true,
        ]);

        // Producto C con precio 0 o inválido
        $p3 = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'SKU-C',
            'nombre' => 'Item C',
            'precio_divisa' => 0.00,
            'precio_bs' => 0.00,
            'is_active' => true,
        ]);

        // Aplicar sobre base DIVISA
        $result = $this->calculatorService->apply(
            [$p1->id, $p2->id, $p3->id],
            'fixed_markup',
            'divisa',
            ['amount' => 5.00]
        );

        $this->assertEquals(3, $result['total_selected']);
        $this->assertEquals(1, $result['updated']); // Solo p1
        $this->assertEquals(1, $result['skipped_missing_price']); // p2 (null)
        $this->assertEquals(1, $result['skipped_invalid_price']); // p3 (0)

        $p1->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertEquals(15.00, (float) $p1->precio_venta_divisa);
        $this->assertNull($p2->precio_venta_divisa);
        $this->assertNull($p3->precio_venta_divisa);
    }

    public function test_web_endpoint_applies_sale_prices_and_redirects(): void
    {
        $product = Product::create([
            'supplier_id' => $this->supplier->id,
            'catalog_id' => $this->catalog->id,
            'codigo' => 'WEB-001',
            'nombre' => 'Juego de Brocas 10 pcs',
            'precio_divisa' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->post(route('sale-prices.apply'), [
            'formula_id' => 'direct_multiplier',
            'base' => 'divisa',
            'product_ids' => [$product->id],
            'parameters' => [
                'multiplier' => 1.40,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals(28.00, (float) $product->precio_venta_divisa);
        $this->assertEquals('direct_multiplier', $product->sale_price_formula);
        $this->assertEquals('divisa', $product->sale_price_base);
    }

    public function test_web_endpoint_validates_required_fields(): void
    {
        $response = $this->post(route('sale-prices.apply'), [
            // missing parameters
        ]);

        $response->assertSessionHasErrors(['formula_id', 'base', 'product_ids']);
    }
}
