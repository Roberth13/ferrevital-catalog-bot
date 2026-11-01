<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingRuleController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::withCount('products')->orderBy('name')->get();
        $totalProducts = Product::count();
        $productsWithSalePrice = Product::whereNotNull('precio_venta_usd')->orWhereNotNull('precio_venta_bs')->count();

        return view('pricing.index', compact('suppliers', 'totalProducts', 'productsWithSalePrice'));
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'base_price' => ['required', 'in:divisa,bs'],
            'formula' => ['required', 'in:margin_percent,markup_percent,multiplier,fixed_add,bcv_tasa'],
            'value' => ['required', 'numeric', 'min:0'],
            'target_price' => ['required', 'in:precio_venta_usd,precio_venta_bs'],
            'round_cents' => ['nullable', 'boolean'],
        ]);

        $query = Product::query();

        if (!empty($validated['supplier_id'])) {
            $query->where('supplier_id', $validated['supplier_id']);
        }

        $baseCol = $validated['base_price'] === 'divisa' ? 'precio_divisa' : 'precio_bs';
        $targetCol = $validated['target_price'];
        $formula = $validated['formula'];
        $val = (float) $validated['value'];
        $roundCents = !empty($validated['round_cents']);

        // Solo productos que tengan el precio base
        $query->whereNotNull($baseCol)->where($baseCol, '>', 0);

        $updatedCount = 0;

        $query->chunkById(500, function ($products) use ($baseCol, $targetCol, $formula, $val, $roundCents, &$updatedCount) {
            foreach ($products as $p) {
                $base = (float) $p->{$baseCol};
                $calc = 0.0;

                switch ($formula) {
                    case 'margin_percent':
                        // Precio = Costo * (1 + Margen/100)  (ej. Costo 10 + 30% = 13)
                        $calc = $base * (1 + ($val / 100));
                        break;

                    case 'markup_percent':
                        // Margen sobre venta: Costo / (1 - Margen/100) (ej. Margen 30% real)
                        if ($val >= 100) {
                            $calc = $base * 2;
                        } else {
                            $calc = $base / (1 - ($val / 100));
                        }
                        break;

                    case 'multiplier':
                        // Multiplicador directo (ej. Costo * 1.35)
                        $calc = $base * $val;
                        break;

                    case 'fixed_add':
                        // Suma fija (ej. Costo + $5)
                        $calc = $base + $val;
                        break;

                    case 'bcv_tasa':
                        // Multiplicar base USD por Tasa (ej. Tasa 45.50 Bs/$)
                        $calc = $base * $val;
                        break;
                }

                if ($roundCents) {
                    // Redondeo comercial a entero (ej. 13.20 -> 14.00 o ceil/round)
                    $calc = ceil($calc);
                } else {
                    $calc = round($calc, 2);
                }

                $p->{$targetCol} = $calc;
                $p->save();
                $updatedCount++;
            }
        });

        $msg = "Se actualizaron correctamente {$updatedCount} precios de venta ({$targetCol}).";
        return redirect()->route('pricing.index')->with('status', $msg);
    }
}

