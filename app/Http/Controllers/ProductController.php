<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\AiProductRanker;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('supplier');

        if ($request->filled('q')) {
            $words = array_filter(explode(' ', trim($request->input('q'))));
            foreach ($words as $word) {
                $query->where(function ($sub) use ($word) {
                    $sub->where('codigo', 'like', "%{$word}%")
                        ->orWhere('nombre', 'like', "%{$word}%")
                        ->orWhere('descripcion_tecnica', 'like', "%{$word}%")
                        ->orWhere('variante', 'like', "%{$word}%");
                });
            }
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->boolean('revisar')) {
            $query->where('revisar', true);
        }

        $aiRankingMap = [];
        $aiRankRequested = $request->boolean('ai_rank') && $request->filled('q');

        if ($aiRankRequested) {
            $matchedProducts = $query->take(30)->get();
            $ranker = new AiProductRanker();
            $rankedItems = $ranker->rank($matchedProducts, (string) $request->input('q'));

            foreach ($rankedItems as $position => $item) {
                $aiRankingMap[$item['id']] = [
                    'posicion' => $position + 1,
                    'motivo' => $item['motivo'],
                ];
            }

            // Ordenar la colección según el ranking de IA
            $sorted = $matchedProducts->sortBy(function ($p) use ($aiRankingMap) {
                return $aiRankingMap[$p->id]['posicion'] ?? 999;
            })->values();

            // Paginador manual para mantener compatibilidad
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $sorted,
                $sorted->count(),
                50,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $products = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();
        }

        $suppliers = Supplier::orderBy('name')->get();

        return view('products.index', compact('products', 'suppliers', 'aiRankingMap', 'aiRankRequested'));
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
            'precio_venta_usd' => $product->precio_venta_usd ? number_format($product->precio_venta_usd, 2, '.', '') : '—',
            'precio_venta_bs' => $product->precio_venta_bs ? number_format($product->precio_venta_bs, 2, ',', '.') : '—',
            'otros_precios' => $product->otros_precios ?: '—',
            'descripcion_tecnica' => $product->descripcion_tecnica ?: '—',
            'garantia' => $product->garantia ?: ($product->supplier?->garantia_default ?: '—'),
            'condiciones_proveedor' => $product->condiciones_proveedor ?: ($product->supplier?->condiciones ?: '—'),
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
            'precio_venta_usd' => ['nullable', 'numeric'],
            'precio_bs' => ['nullable', 'numeric'],
            'precio_venta_bs' => ['nullable', 'numeric'],
            'otros_precios' => ['nullable', 'string'],
            'condiciones_proveedor' => ['nullable', 'string'],
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

    public function exportXlsx(Request $request): BinaryFileResponse
    {
        $query = Product::with('supplier');

        if ($request->filled('q')) {
            $words = array_filter(explode(' ', trim($request->input('q'))));
            foreach ($words as $word) {
                $query->where(function ($sub) use ($word) {
                    $sub->where('codigo', 'like', "%{$word}%")
                        ->orWhere('nombre', 'like', "%{$word}%")
                        ->orWhere('descripcion_tecnica', 'like', "%{$word}%")
                        ->orWhere('variante', 'like', "%{$word}%");
                });
            }
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->boolean('revisar')) {
            $query->where('revisar', true);
        }

        $headers = [
            'ID',
            'Proveedor',
            'Código',
            'Variante',
            'Nombre',
            'Costo USD',
            'PVP Venta USD',
            'Costo Bs',
            'PVP Venta Bs',
            'Otros Precios',
            'Condiciones Producto',
            'Garantía',
            'Página',
            'Revisar',
            'Motivo Revisar',
            'Descripción Técnica',
            'Incluye',
        ];

        $generator = function () use ($query) {
            foreach ($query->lazy(500) as $p) {
                yield [
                    $p->id,
                    $p->supplier?->name ?? '',
                    $p->codigo,
                    $p->variante,
                    $p->nombre,
                    $p->precio_divisa,
                    $p->precio_venta_usd,
                    $p->precio_bs,
                    $p->precio_venta_bs,
                    $p->otros_precios,
                    $p->condiciones_proveedor,
                    $p->garantia,
                    $p->pagina,
                    $p->revisar ? 'SI' : 'NO',
                    $p->revisar_motivo,
                    $p->descripcion_tecnica,
                    $p->incluye,
                ];
            }
        };

        $tmpPath = SimpleXlsxExporter::create($headers, $generator());
        $filename = 'productos_' . date('Y-m-d_His') . '.xlsx';

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
