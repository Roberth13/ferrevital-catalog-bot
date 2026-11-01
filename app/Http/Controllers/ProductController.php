<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('supplier');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->boolean('revisar')) {
            $query->where('revisar', true);
        }

        $products = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get();

        return view('products.index', compact('products', 'suppliers'));
    }

    public function show(Product $product)
    {
        $product->load(['supplier', 'catalog']);
        return response()->json([
            'id' => $product->id,
            'codigo' => $product->codigo,
            'variante' => $product->variante,
            'nombre' => $product->nombre,
            'precio_bs' => $product->precio_bs ? number_format($product->precio_bs, 2, ',', '.') : '—',
            'precio_divisa' => $product->precio_divisa ? number_format($product->precio_divisa, 2, '.', '') : '—',
            'descripcion_tecnica' => $product->descripcion_tecnica ?: '—',
            'garantia' => $product->garantia ?: '—',
            'condiciones_proveedor' => $product->supplier?->condiciones ?: '—',
            'tiempo_entrega' => $product->supplier?->tiempo_entrega ?: '—',
            'incluye' => $product->incluye ?: '—',
            'revisar' => $product->revisar,
            'revisar_motivo' => $product->revisar_motivo,
            'pagina' => $product->pagina,
        ]);
    }

    public function edit(Product $product)
    {
        $suppliers = Supplier::orderBy('name')->get();
        return view('products.edit', compact('product', 'suppliers'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:255'],
            'variante' => ['nullable', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:500'],
            'precio_divisa' => ['nullable', 'numeric'],
            'precio_bs' => ['nullable', 'numeric'],
            'descripcion_tecnica' => ['nullable', 'string'],
            'incluye' => ['nullable', 'string'],
            'garantia' => ['nullable', 'string', 'max:255'],
            'pagina' => ['nullable', 'integer'],
        ]);

        $validated['variante'] = $validated['variante'] ?? '';
        $validated['revisar'] = false;
        $validated['revisar_motivo'] = null;

        $product->update($validated);

        return redirect()->route('products.index')->with('status', 'Producto actualizado correctamente.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Product::with('supplier');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->boolean('revisar')) {
            $query->where('revisar', true);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="productos_' . date('Y-m-d_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Proveedor',
                'Código',
                'Variante',
                'Nombre',
                'Precio Divisa (USD)',
                'Precio Bs',
                'Garantía',
                'Página',
                'Revisar',
                'Motivo Revisar',
                'Descripción Técnica',
                'Incluye',
            ]);

            $query->chunk(500, function ($batch) use ($handle) {
                foreach ($batch as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->supplier?->name ?? '',
                        $p->codigo,
                        $p->variante,
                        $p->nombre,
                        $p->precio_divisa,
                        $p->precio_bs,
                        $p->garantia,
                        $p->pagina,
                        $p->revisar ? 'SI' : 'NO',
                        $p->revisar_motivo,
                        $p->descripcion_tecnica,
                        $p->incluye,
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
