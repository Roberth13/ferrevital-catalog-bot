<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Catalog;
use App\Models\Supplier;
use App\Services\ProductSearchService;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSearchService $searchService
    ) {
    }

    public function index(Request $request)
    {
        $products = $this->searchService->search($request->all());
        $catalogs = Catalog::orderBy('created_at', 'desc')->get();
        $suppliers = Supplier::orderBy('name', 'asc')->get();
        $perPage = $request->input('per_page', 20);

        return view('products.index', compact('products', 'catalogs', 'suppliers', 'perPage'));
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
        $query = Product::with(['catalog', 'supplier']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('catalog_id')) {
            $query->where('catalog_id', $request->catalog_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $fileName = 'productos_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') . '.xlsx';

        $writer = SimpleExcelWriter::create($tempPath);

        $query->chunk(1000, function ($products) use ($writer) {
            foreach ($products as $product) {
                $writer->addRow([
                    'Proveedor' => $product->supplier ? $product->supplier->name : 'Proveedor Genérico',
                    'Código' => $product->codigo,
                    'Nombre' => $product->nombre,
                    'Precio Costo Divisa' => $product->precio_divisa,
                    'Precio Costo Bs' => $product->precio_bs,
                    'Precio Venta Divisa' => $product->precio_venta_divisa,
                    'Precio Venta Bs' => $product->precio_venta_bs,
                    'Fórmula Venta' => $product->sale_price_formula ?? '',
                    'Base Venta' => $product->sale_price_base ?? '',
                    'Descripción técnica' => $product->descripcion ?? '',
                    'Garantía' => $product->garantia ?? '',
                    'Condiciones proveedor' => $product->condiciones ?? '',
                    'Tiempo entrega' => $product->tiempo_entrega ?? '',
                    'Método extracción' => $product->extraction_method ?? '',
                    'Página' => $product->page_number ?? '',
                    'Catálogo' => $product->catalog ? $product->catalog->original_filename : 'N/A',
                    'Estado' => $product->is_active ? 'Activo' : 'Inactivo',
                ]);
            }
        });

        $writer->close();

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
