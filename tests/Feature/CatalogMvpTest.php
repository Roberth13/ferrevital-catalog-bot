<?php

namespace Tests\Feature;

use App\Jobs\ProcessCatalog;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogMvpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_empleado_can_view_products_but_forbidden_from_admin_routes(): void
    {
        $empleado = User::where('email', 'empleado@example.com')->firstOrFail();

        // Empleado ve productos
        $response = $this->actingAs($empleado)->get('/products');
        $response->assertStatus(200);

        // Empleado recibe 403 en catálogos
        $responseCatalogs = $this->actingAs($empleado)->get('/catalogs');
        $responseCatalogs->assertStatus(403);

        // Empleado recibe 403 en proveedores
        $responseSuppliers = $this->actingAs($empleado)->get('/suppliers');
        $responseSuppliers->assertStatus(403);

        // Crear producto para prueba de edición
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $product = Product::create([
            'supplier_id' => $supplier->id,
            'codigo' => 'PROD-1',
            'variante' => '',
            'nombre' => 'Producto Test',
            'precio_divisa' => 10.00,
        ]);

        // Empleado recibe 403 en editar producto
        $responseEdit = $this->actingAs($empleado)->get("/products/{$product->id}/edit");
        $responseEdit->assertStatus(403);
    }

    public function test_admin_can_access_all_modules(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)->get('/products')->assertStatus(200);
        $this->actingAs($admin)->get('/catalogs')->assertStatus(200);
        $this->actingAs($admin)->get('/suppliers')->assertStatus(200);
    }

    public function test_reuploading_same_catalog_updates_products_without_duplicates(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier A']);
        $catalog1 = Catalog::create([
            'supplier_id' => $supplier->id,
            'original_name' => 'cat1.pdf',
            'path' => 'catalogs/cat1.pdf',
            'status' => 'listo',
        ]);

        // Simular primera inserción
        Product::updateOrCreate(
            ['supplier_id' => $supplier->id, 'codigo' => 'ABC-100', 'variante' => 'T1'],
            [
                'catalog_id' => $catalog1->id,
                'nombre' => 'Taladro V1',
                'precio_divisa' => 50.00,
            ]
        );

        $this->assertEquals(1, Product::count());

        $catalog2 = Catalog::create([
            'supplier_id' => $supplier->id,
            'original_name' => 'cat1_v2.pdf',
            'path' => 'catalogs/cat1_v2.pdf',
            'status' => 'listo',
        ]);

        // Simular actualización del mismo catálogo con nuevo precio
        Product::updateOrCreate(
            ['supplier_id' => $supplier->id, 'codigo' => 'ABC-100', 'variante' => 'T1'],
            [
                'catalog_id' => $catalog2->id,
                'nombre' => 'Taladro V1 Actualizado',
                'precio_divisa' => 55.00,
            ]
        );

        $this->assertEquals(1, Product::count());
        $updatedProd = Product::first();
        $this->assertEquals('Taladro V1 Actualizado', $updatedProd->nombre);
        $this->assertEquals(55.00, (float) $updatedProd->precio_divisa);
        $this->assertEquals($catalog2->id, $updatedProd->catalog_id);
    }

    public function test_catalog_status_marked_error_if_pdf_does_not_exist(): void
    {
        Storage::fake('local');

        $supplier = Supplier::create(['name' => 'Ghost Supplier']);
        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'original_name' => 'no_existe.pdf',
            'path' => 'catalogs/no_existe.pdf',
            'status' => 'pendiente',
        ]);

        $job = new ProcessCatalog($catalog);
        $job->handle();

        $catalog->refresh();
        $this->assertEquals('error', $catalog->status);
        $this->assertStringContainsString('no existe', $catalog->message);
    }

    public function test_product_detail_endpoint_returns_json(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::create([
            'name' => 'Proveedor Detalle',
            'condiciones' => 'Contado',
            'tiempo_entrega' => '24h',
            'garantia_default' => '1 ano',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'codigo' => 'DET-01',
            'variante' => 'V1',
            'nombre' => 'Esmeril Angular',
            'precio_divisa' => 89.50,
            'precio_bs' => 3500.00,
            'descripcion_tecnica' => '115mm 900W',
            'incluye' => 'Llave y mango',
            'garantia' => '1 ano',
            'revisar' => true,
            'revisar_motivo' => 'falta codigo',
        ]);

        $response = $this->actingAs($admin)->getJson("/products/{$product->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'codigo' => 'DET-01',
            'variante' => 'V1',
            'nombre' => 'Esmeril Angular',
            'precio_divisa' => '89.50',
            'condiciones_proveedor' => 'Contado',
            'tiempo_entrega' => '24h',
            'incluye' => 'Llave y mango',
            'revisar' => true,
        ]);
    }

    public function test_editing_product_clears_revisar_flag(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::create(['name' => 'Supplier Rev']);
        $product = Product::create([
            'supplier_id' => $supplier->id,
            'codigo' => 'REV-01',
            'variante' => '',
            'nombre' => 'Producto Sin Revisar',
            'precio_divisa' => 20.00,
            'revisar' => true,
            'revisar_motivo' => 'falta precio divisa',
        ]);

        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'codigo' => 'REV-01',
            'variante' => '',
            'nombre' => 'Producto Corregido',
            'precio_divisa' => 25.00,
            'precio_bs' => 1000.00,
        ]);

        $response->assertRedirect('/products');

        $product->refresh();
        $this->assertFalse($product->revisar);
        $this->assertNull($product->revisar_motivo);
        $this->assertEquals(25.00, (float) $product->precio_divisa);
    }
}

