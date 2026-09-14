<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount(['catalogs', 'products'])
            ->orderBy('name', 'asc')
            ->get();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:suppliers,slug',
        ], [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'slug.unique' => 'Ya existe un proveedor con ese identificador (slug).',
        ]);

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        if (empty($validated['slug']) && Supplier::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . (Supplier::where('slug', 'like', "{$slug}%")->count() + 1);
        }

        Supplier::create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Proveedor '{$validated['name']}' creado correctamente.");
    }
}
