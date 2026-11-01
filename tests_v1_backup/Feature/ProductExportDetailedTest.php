<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class ProductExportDetailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_filtered_products_by_search_supplier_and_catalog(): void
    {
        $supplierA = Supplier::create(['name' => 'Supplier Alpha', 'slug' => 'supplier-alpha']);
        $supplierB = Supplier::create(['name' => 'Supplier Beta', 'slug' => 'supplier-beta']);

        $catalogA = Catalog::create([
            'supplier_id' => $supplierA->id,
            'filename' => 'catalogs/catA.pdf',
            'original_filename' => 'CatA.pdf',
            'status' => 'completed',
        ]);

        $catalogB = Catalog::create([
            'supplier_id' => $supplierB->id,
            'filename' => 'catalogs/catB.pdf',
            'original_filename' => 'CatB.pdf',
            'status' => 'completed',
        ]);

        Product::create([
            'supplier_id' => $supplierA->id,
            'catalog_id' => $catalogA->id,
            'codigo' => 'TAL-001',
            'nombre' => 'Taladro Percutor 20V',
            'precio_divisa' => 59.99,
            'precio_bs' => 2100.00,
            'descripcion' => 'Motor sin escobillas',
            'garantia' => '1 año',
            'condiciones' => 'Entrega inmediata',
            'tiempo_entrega' => '24h',
            'extraction_method' => 'text',
            'page_number' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'supplier_id' => $supplierA->id,
            'catalog_id' => $catalogA->id,
            'codigo' => 'AMO-002',
            'nombre' => 'Amoladora Angular 4-1/2',
            'precio_divisa' => 35.50,
            'precio_bs' => 1240.00,
            'extraction_method' => 'text',
            'page_number' => 8,
            'is_active' => true,
        ]);

        Product::create([
            'supplier_id' => $supplierB->id,
            'catalog_id' => $catalogB->id,
            'codigo' => 'TAL-003',
            'nombre' => 'Taladro Inalámbrico Beta',
            'precio_divisa' => 75.00,
            'precio_bs' => 2600.00,
            'extraction_method' => 'ocr',
            'page_number' => 2,
            'is_active' => true,
        ]);

        // 1. Export filtered by supplier_id = supplierA
        $responseA = $this->get(route('products.export', ['supplier_id' => $supplierA->id]));
        $responseA->assertStatus(200);
        $responseA->assertHeader('content-disposition');

        // 2. Export filtered by search = 'Taladro'
        $responseSearch = $this->get(route('products.export', ['search' => 'Taladro']));
        $responseSearch->assertStatus(200);

        // 3. Export filtered by catalog_id = catalogB
        $responseCatalog = $this->get(route('products.export', ['catalog_id' => $catalogB->id]));
        $responseCatalog->assertStatus(200);
    }
}
