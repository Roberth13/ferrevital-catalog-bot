<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class ProductExplorerSalePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_explorer_renders_products_with_and_without_sale_prices(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Explorer', 'slug' => 'supplier-explorer']);
        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/exp.pdf',
            'original_filename' => 'exp.pdf',
            'status' => 'completed',
        ]);

        // Product with sale prices
        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'EXP-001',
            'nombre' => 'Producto con Precio Venta',
            'precio_divisa' => 50.00,
            'precio_bs' => 1800.00,
            'precio_venta_divisa' => 65.00,
            'precio_venta_bs' => 2340.00,
            'sale_price_formula' => 'percentage_markup',
            'sale_price_base' => 'divisa',
            'is_active' => true,
        ]);

        // Product without sale prices (nulls)
        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'EXP-002',
            'nombre' => 'Producto sin Precio Venta',
            'precio_divisa' => 12.00,
            'precio_bs' => null,
            'precio_venta_divisa' => null,
            'precio_venta_bs' => null,
            'is_active' => true,
        ]);

        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        $response->assertSee('EXP-001');
        $response->assertSee('EXP-002');
        $response->assertSee('$65.00');
        $response->assertSee('Bs 2,340.00');

        $salePriceViewResponse = $this->get(route('sale-prices.index'));
        $salePriceViewResponse->assertStatus(200);
        $salePriceViewResponse->assertSee('EXP-001');
        $salePriceViewResponse->assertSee('EXP-002');
    }

    public function test_excel_export_includes_cost_and_sale_price_columns(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Excel', 'slug' => 'supplier-excel']);
        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/exp_excel.pdf',
            'original_filename' => 'exp_excel.pdf',
            'status' => 'completed',
        ]);

        Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog->id,
            'codigo' => 'EXCEL-SKU',
            'nombre' => 'Item Exportable',
            'precio_divisa' => 100.00,
            'precio_bs' => 3600.00,
            'precio_venta_divisa' => 130.00,
            'precio_venta_bs' => null,
            'sale_price_formula' => 'percentage_markup',
            'sale_price_base' => 'divisa',
            'is_active' => true,
        ]);

        $response = $this->get(route('products.export'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
