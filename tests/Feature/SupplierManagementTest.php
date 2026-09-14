<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_suppliers(): void
    {
        Supplier::create(['name' => 'Supplier Alpha', 'slug' => 'supplier-alpha']);
        Supplier::create(['name' => 'Supplier Beta', 'slug' => 'supplier-beta']);

        $response = $this->get(route('suppliers.index'));

        $response->assertStatus(200);
        $response->assertSee('Supplier Alpha');
        $response->assertSee('Supplier Beta');
        $response->assertSee('supplier-alpha');
        $response->assertSee('supplier-beta');
    }

    public function test_it_creates_new_supplier_with_custom_slug(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Dong Cheng Tools',
            'slug' => 'dong-cheng-tools',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Dong Cheng Tools',
            'slug' => 'dong-cheng-tools',
        ]);
    }

    public function test_it_creates_new_supplier_with_auto_generated_slug(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Jadever Hardware & Tools',
            'slug' => '',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Jadever Hardware & Tools',
            'slug' => 'jadever-hardware-tools',
        ]);
    }

    public function test_it_validates_required_name(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => '',
            'slug' => 'some-slug',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_it_prevents_duplicate_slug_when_explicitly_provided(): void
    {
        Supplier::create(['name' => 'Existing', 'slug' => 'existing-slug']);

        $response = $this->post(route('suppliers.store'), [
            'name' => 'New Name',
            'slug' => 'existing-slug',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_generic_supplier_helper_methods(): void
    {
        $generic = Supplier::getGenericSupplier();
        $this->assertEquals('proveedor-generico', $generic->slug);
        $this->assertEquals($generic->id, Supplier::getGenericSupplierId());
    }
}
