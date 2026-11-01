<?php

namespace Tests\Unit;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_suppliers_can_have_the_same_product_code()
    {
        $supplierA = Supplier::create(['name' => 'Supplier A', 'slug' => 'supplier-a']);
        $supplierB = Supplier::create(['name' => 'Supplier B', 'slug' => 'supplier-b']);

        $catalogA = Catalog::create([
            'supplier_id' => $supplierA->id,
            'filename' => 'cat_a.pdf',
            'original_filename' => 'cat_a.pdf',
            'status' => 'completed',
        ]);

        $catalogB = Catalog::create([
            'supplier_id' => $supplierB->id,
            'filename' => 'cat_b.pdf',
            'original_filename' => 'cat_b.pdf',
            'status' => 'completed',
        ]);

        $productA = Product::create([
            'supplier_id' => $supplierA->id,
            'catalog_id' => $catalogA->id,
            'codigo' => 'SKU-001',
            'nombre' => 'Producto Supplier A',
            'precio_divisa' => 10.50,
            'is_active' => true,
        ]);

        $productB = Product::create([
            'supplier_id' => $supplierB->id,
            'catalog_id' => $catalogB->id,
            'codigo' => 'SKU-001',
            'nombre' => 'Producto Supplier B',
            'precio_divisa' => 25.00,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'supplier_id' => $supplierA->id,
            'codigo' => 'SKU-001',
            'precio_divisa' => 10.50,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'supplier_id' => $supplierB->id,
            'codigo' => 'SKU-001',
            'precio_divisa' => 25.00,
        ]);

        $this->assertEquals(2, Product::where('codigo', 'SKU-001')->count());
    }

    public function test_same_supplier_upserts_existing_product_code()
    {
        $supplier = Supplier::create(['name' => 'DongCheng', 'slug' => 'dongcheng']);

        $catalog1 = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'cat_jan.pdf',
            'original_filename' => 'cat_jan.pdf',
            'status' => 'completed',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog1->id,
            'codigo' => 'DC-100',
            'nombre' => 'Taladro Jan',
            'precio_divisa' => 50.00,
            'is_active' => true,
        ]);

        $catalog2 = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'cat_feb.pdf',
            'original_filename' => 'cat_feb.pdf',
            'status' => 'completed',
        ]);

        // Simular upsert de nuevo catálogo del mismo proveedor
        \Illuminate\Support\Facades\DB::table('products')->upsert([
            [
                'supplier_id' => $supplier->id,
                'catalog_id' => $catalog2->id,
                'codigo' => 'DC-100',
                'nombre' => 'Taladro Feb (Actualizado)',
                'precio_divisa' => 55.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ], ['supplier_id', 'codigo'], ['nombre', 'precio_divisa', 'catalog_id', 'updated_at']);

        $this->assertEquals(1, Product::where('supplier_id', $supplier->id)->where('codigo', 'DC-100')->count());
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'supplier_id' => $supplier->id,
            'catalog_id' => $catalog2->id,
            'codigo' => 'DC-100',
            'nombre' => 'Taladro Feb (Actualizado)',
            'precio_divisa' => 55.00,
        ]);
    }
}
