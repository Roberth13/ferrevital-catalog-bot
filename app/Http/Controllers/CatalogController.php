<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Supplier;
use App\Jobs\ProcessCatalogJob;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $catalogs = Catalog::with('supplier')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('catalogs.index', compact('catalogs'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name', 'asc')->get();

        return view('catalogs.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'exists:suppliers,id',
            ],
            'pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:153600', // 150MB
            ],
        ], [
            'pdf.required' => 'Debes seleccionar un archivo PDF.',
            'pdf.file' => 'El archivo subido no es válido.',
            'pdf.mimes' => 'El archivo debe ser un documento PDF válido.',
            'pdf.max' => 'El archivo no debe exceder los 150 MB.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
        ]);

        $file = $validated['pdf'];

        // Validación adicional de tipo MIME seguro
        $mime = $file->getMimeType();
        $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/pdf', 'text/pdf', 'text/x-pdf'];
        if (!in_array(strtolower($mime), $allowedMimes, true)) {
            return back()->withErrors(['pdf' => 'El archivo debe ser un documento PDF válido.'])->withInput();
        }

        $supplierId = !empty($validated['supplier_id'])
            ? (int) $validated['supplier_id']
            : Supplier::getGenericSupplierId();

        $filename = $file->hashName();

        $path = $file->storeAs(
            'catalogs',
            $filename,
            'local'
        );

        $catalog = Catalog::create([
            'supplier_id' => $supplierId,
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'pending',
            'total_products' => 0,
        ]);

        ProcessCatalogJob::dispatch($catalog);

        return redirect()
            ->route('catalogs.show', $catalog)
            ->with(
                'success',
                "Catálogo #{$catalog->id} subido correctamente. El procesamiento asíncrono ha iniciado."
            );
    }

    public function show(Catalog $catalog)
    {
        $catalog->load('supplier');

        $products = $catalog->products()
            ->orderBy('page_number', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(25);

        return view('catalogs.show', compact('catalog', 'products'));
    }
}