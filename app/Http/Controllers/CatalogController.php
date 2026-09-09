<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Jobs\ProcessCatalogJob;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function create()
    {
        return view('catalogs.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:153600', // 150MB
            ],
        ]);

        $file = $validated['pdf'];
        $filename = $file->hashName();

        $path = $file->storeAs(
            'catalogs',
            $filename,
            'local'
        );

        $catalog = Catalog::create([
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'uploaded',
        ]);

        ProcessCatalogJob::dispatch($catalog);

        return redirect()
            ->route('catalogs.create')
            ->with(
                'success',
                "Catálogo #{$catalog->id} subido. Se está procesando en segundo plano."
            );
    }
}