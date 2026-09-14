<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductSearchService;
use App\Services\SalePrice\SalePriceCalculatorService;
use App\Services\SalePrice\SalePriceFormulaRegistry;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SalePriceController extends Controller
{
    public function __construct(
        protected readonly ProductSearchService $searchService,
        protected readonly SalePriceCalculatorService $calculatorService,
        protected readonly SalePriceFormulaRegistry $formulaRegistry
    ) {
    }

    public function index(Request $request)
    {
        $products = $this->searchService->search($request->all());
        $catalogs = Catalog::orderBy('created_at', 'desc')->get();
        $suppliers = Supplier::orderBy('name', 'asc')->get();
        $formulas = $this->formulaRegistry->all();
        $perPage = $request->input('per_page', 20);

        return view('sale_prices.index', compact(
            'products',
            'catalogs',
            'suppliers',
            'formulas',
            'perPage'
        ));
    }

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'formula_id' => 'required|string',
            'base' => 'required|in:bs,divisa',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'parameters' => 'nullable|array',
        ]);

        try {
            $result = $this->calculatorService->apply(
                $validated['product_ids'],
                $validated['formula_id'],
                $validated['base'],
                $validated['parameters'] ?? []
            );

            $msg = "Se calcularon y actualizaron exitosamente {$result['updated']} precios de venta utilizando la fórmula '{$result['formula_name']}' sobre base " . strtoupper($result['base']) . ".";
            
            if ($result['skipped_missing_price'] > 0 || $result['skipped_invalid_price'] > 0) {
                $skippedTotal = $result['skipped_missing_price'] + $result['skipped_invalid_price'];
                $msg .= " ({$skippedTotal} productos fueron omitidos por no poseer un precio de costo válido en " . strtoupper($result['base']) . ").";
            }

            return redirect()->back()->with('success', $msg)->with('calculation_result', $result);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'Ocurrió un error inesperado al calcular los precios: ' . $e->getMessage()])->withInput();
        }
    }
}
