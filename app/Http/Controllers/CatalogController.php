<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCatalog;
use App\Models\Catalog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function index()
    {
        $catalogs = Catalog::with('supplier')->orderBy('id', 'desc')->get();
        $hasActive = $catalogs->contains(fn ($c) => in_array($c->status, ['pendiente', 'procesando']));

        return view('catalogs.index', compact('catalogs', 'hasActive'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:204800'], // 200 MB
            'first_page' => ['nullable', 'integer', 'min:1'],
            'last_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $supplier = Supplier::firstOrCreate([
            'name' => trim($request->input('supplier_name')),
        ]);

        $file = $request->file('pdf');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('catalogs', 'local');

        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'original_name' => $originalName,
            'path' => $path,
            'first_page' => $request->filled('first_page') ? (int) $request->input('first_page') : null,
            'last_page' => $request->filled('last_page') ? (int) $request->input('last_page') : null,
            'status' => 'pendiente',
        ]);

        ProcessCatalog::dispatch($catalog);

        return redirect()->route('catalogs.index')->with('status', 'Catálogo subido. Extracción en cola iniciada.');
    }
}
