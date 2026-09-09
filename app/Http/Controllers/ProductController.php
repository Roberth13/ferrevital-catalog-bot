<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('catalog');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        if ($request->filled('catalog_id')) {
            $query->where('catalog_id', $request->catalog_id);
        }

        $perPage = $request->input('per_page', 20);
        $perPageInt = $perPage === 'all' ? ($query->count() ?: 1) : (int) $perPage;

        if ($request->filled('search')) {
            // Limitar a 100 resultados para no exceder tokens de IA
            $allMatching = $query->limit(100)->get();
            
            if ($allMatching->count() > 0) {
                $ranker = app(\App\Services\AiProductRanker::class);
                $rankedIds = $ranker->rankByValueForMoney($allMatching, $request->search);
                
                // Reordenar la colección según la IA
                $sortedProducts = $allMatching->sortBy(function($model) use ($rankedIds) {
                    return array_search($model->id, $rankedIds);
                })->values();

                // Paginación manual
                $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
                $products = new \Illuminate\Pagination\LengthAwarePaginator(
                    $sortedProducts->forPage($page, $perPageInt),
                    $sortedProducts->count(),
                    $perPageInt,
                    $page,
                    ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
                );
                $products->withQueryString();
            } else {
                $products = $query->paginate($perPageInt)->withQueryString();
            }
        } else {
            $products = $query->paginate($perPageInt)->withQueryString();
        }

        $catalogs = \App\Models\Catalog::orderBy('created_at', 'desc')->get();

        return view('products.index', compact('products', 'catalogs', 'perPage'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'precio_divisa' => 'required|numeric|min:0',
            'precio_bs' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        
        return redirect()->route('products.index')->with('success', 'Producto eliminado correctamente.');
    }

    public function toggleActive(Product $product)
    {
        $product->update([
            'is_active' => !$product->is_active
        ]);

        $status = $product->is_active ? 'activado' : 'desactivado';
        
        return redirect()->route('products.index')->with('success', "Producto {$status} correctamente.");
    }

    public function export(Request $request)
    {
        $query = Product::with('catalog');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        if ($request->filled('catalog_id')) {
            $query->where('catalog_id', $request->catalog_id);
        }

        $fileName = 'productos_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        $writer = SimpleExcelWriter::streamDownload($fileName);

        $query->chunk(1000, function ($products) use ($writer) {
            foreach ($products as $product) {
                $writer->addRow([
                    'Código' => $product->codigo,
                    'Nombre' => $product->nombre,
                    'Precio Divisa' => $product->precio_divisa,
                    'Precio Bs' => $product->precio_bs,
                    'Catálogo' => $product->catalog ? $product->catalog->original_filename : 'N/A',
                    'Estado' => $product->is_active ? 'Activo' : 'Inactivo',
                ]);
            }
        });

        return $writer->toBrowser();
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,activate,deactivate',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $ids = $validated['product_ids'];
        $action = $validated['action'];
        $count = count($ids);

        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();
            $msg = "{$count} productos eliminados correctamente.";
        } elseif ($action === 'activate') {
            Product::whereIn('id', $ids)->update(['is_active' => true]);
            $msg = "{$count} productos activados correctamente.";
        } elseif ($action === 'deactivate') {
            Product::whereIn('id', $ids)->update(['is_active' => false]);
            $msg = "{$count} productos desactivados correctamente.";
        }

        return redirect()->back()->with('success', $msg);
    }
}
