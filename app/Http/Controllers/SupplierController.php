<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('products', 'catalogs')->orderBy('name')->get();
        return view('suppliers.index', compact('suppliers'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'condiciones' => ['nullable', 'string'],
            'tiempo_entrega' => ['nullable', 'string', 'max:255'],
            'garantia_default' => ['nullable', 'string', 'max:255'],
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('status', 'Proveedor actualizado correctamente.');
    }
}
