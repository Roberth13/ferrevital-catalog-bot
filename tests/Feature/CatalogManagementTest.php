<?php

namespace Tests\Feature;

use App\Jobs\ProcessCatalogJob;
use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_catalogs(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier X', 'slug' => 'supplier-x']);
        Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/cat1.pdf',
            'original_filename' => 'Catalogo_2026.pdf',
            'status' => 'completed',
            'total_products' => 45,
        ]);

        $response = $this->get(route('catalogs.index'));

        $response->assertStatus(200);
        $response->assertSee('Supplier X');
        $response->assertSee('Catalogo_2026.pdf');
        $response->assertSee('Completado');
        $response->assertSee('45');
    }

    public function test_it_shows_catalog_create_form_with_suppliers(): void
    {
        Supplier::create(['name' => 'Brand Dong Cheng', 'slug' => 'dong-cheng']);

        $response = $this->get(route('catalogs.create'));

        $response->assertStatus(200);
        $response->assertSee('Brand Dong Cheng');
        $response->assertSee('Proveedor Genérico (por defecto)');
    }

    public function test_it_uploads_pdf_and_creates_pending_catalog_with_assigned_supplier(): void
    {
        Storage::fake('local');
        Queue::fake();

        $supplier = Supplier::create(['name' => 'Jadever', 'slug' => 'jadever']);
        $file = UploadedFile::fake()->create('jadever_2026.pdf', 1024, 'application/pdf');

        $response = $this->post(route('catalogs.store'), [
            'supplier_id' => $supplier->id,
            'pdf' => $file,
        ]);

        $this->assertDatabaseHas('catalogs', [
            'supplier_id' => $supplier->id,
            'original_filename' => 'jadever_2026.pdf',
            'status' => 'pending',
            'total_products' => 0,
        ]);

        $catalog = Catalog::first();
        $response->assertRedirect(route('catalogs.show', $catalog));
        Storage::disk('local')->assertExists($catalog->filename);
        Queue::assertPushed(ProcessCatalogJob::class, function ($job) use ($catalog) {
            return $job->catalog->id === $catalog->id;
        });
    }

    public function test_it_uploads_pdf_and_falls_back_to_generic_supplier_when_unspecified(): void
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('unassigned_catalog.pdf', 500, 'application/pdf');

        $response = $this->post(route('catalogs.store'), [
            'supplier_id' => '',
            'pdf' => $file,
        ]);

        $genericId = Supplier::getGenericSupplierId();

        $this->assertDatabaseHas('catalogs', [
            'supplier_id' => $genericId,
            'original_filename' => 'unassigned_catalog.pdf',
            'status' => 'pending',
        ]);

        $catalog = Catalog::first();
        $response->assertRedirect(route('catalogs.show', $catalog));
    }

    public function test_it_rejects_non_pdf_files(): void
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');

        $response = $this->post(route('catalogs.store'), [
            'pdf' => $file,
        ]);

        $response->assertSessionHasErrors(['pdf']);
        $this->assertDatabaseCount('catalogs', 0);
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_invalid_supplier_id(): void
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('test.pdf', 500, 'application/pdf');

        $response = $this->post(route('catalogs.store'), [
            'supplier_id' => 99999,
            'pdf' => $file,
        ]);

        $response->assertSessionHasErrors(['supplier_id']);
        $this->assertDatabaseCount('catalogs', 0);
    }

    public function test_it_shows_catalog_details_and_paginated_products(): void
    {
        $supplier = Supplier::create(['name' => 'Ronix Tools', 'slug' => 'ronix']);
        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/ronix.pdf',
            'original_filename' => 'Ronix_Cat.pdf',
            'status' => 'completed',
            'total_products' => 30,
        ]);

        for ($i = 1; $i <= 30; $i++) {
            Product::create([
                'supplier_id' => $supplier->id,
                'catalog_id' => $catalog->id,
                'codigo' => "RNX-{$i}",
                'nombre' => "Producto Ronix #{$i}",
                'precio_divisa' => 10.00 + $i,
                'precio_bs' => 350.00 + ($i * 35),
                'extraction_method' => 'text',
                'page_number' => ceil($i / 10),
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('catalogs.show', $catalog));

        $response->assertStatus(200);
        $response->assertSee('Ronix Tools');
        $response->assertSee('RNX-1');
        $response->assertSee('Producto Ronix #1');
        $response->assertSee('Productos Extraídos (30)');
    }
}
