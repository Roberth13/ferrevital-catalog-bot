<?php

namespace App\Services\SalePrice;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalePriceCalculatorService
{
    public function __construct(
        protected readonly SalePriceFormulaRegistry $registry
    ) {
    }

    /**
     * Applies a formula calculation to a list of products.
     *
     * @param array<int> $productIds
     * @param string $formulaId
     * @param string $base 'bs' | 'divisa'
     * @param array $parameters
     * @return array{
     *     total_selected: int,
     *     updated: int,
     *     skipped_missing_price: int,
     *     skipped_invalid_price: int,
     *     formula_name: string,
     *     base: string,
     *     products_updated: array
     * }
     */
    public function apply(array $productIds, string $formulaId, string $base, array $parameters): array
    {
        $base = strtolower(trim($base));
        if (!in_array($base, ['bs', 'divisa'], true)) {
            throw new InvalidArgumentException("La base de cálculo debe ser 'bs' o 'divisa'.");
        }

        $formula = $this->registry->get($formulaId);

        if (empty($productIds)) {
            return [
                'total_selected' => 0,
                'updated' => 0,
                'skipped_missing_price' => 0,
                'skipped_invalid_price' => 0,
                'formula_name' => $formula->getName(),
                'base' => $base,
                'products_updated' => [],
            ];
        }

        $products = Product::whereIn('id', $productIds)->get();

        $updatedCount = 0;
        $skippedMissingPrice = 0;
        $skippedInvalidPrice = 0;
        $productsUpdated = [];
        $now = now();

        DB::transaction(function () use (
            $products,
            $formula,
            $formulaId,
            $base,
            $parameters,
            $now,
            &$updatedCount,
            &$skippedMissingPrice,
            &$skippedInvalidPrice,
            &$productsUpdated
        ) {
            foreach ($products as $product) {
                // Get the raw supplier cost according to the selected base
                $rawBasePrice = ($base === 'bs') ? $product->precio_bs : $product->precio_divisa;

                if ($rawBasePrice === null) {
                    $skippedMissingPrice++;
                    continue;
                }

                $basePriceFloat = (float) $rawBasePrice;
                if ($basePriceFloat <= 0) {
                    $skippedInvalidPrice++;
                    continue;
                }

                // Calculate sale price using the formula
                $calculatedSalePrice = $formula->calculate($basePriceFloat, $parameters);

                // Prepare attributes for update
                $updateData = [
                    'sale_price_formula' => $formulaId,
                    'sale_price_base' => $base,
                    'sale_price_applied_at' => $now,
                ];

                if ($base === 'bs') {
                    $updateData['precio_venta_bs'] = $calculatedSalePrice;
                } else {
                    $updateData['precio_venta_divisa'] = $calculatedSalePrice;
                }

                // Persist update without altering supplier raw cost fields
                $product->update($updateData);

                $updatedCount++;
                $productsUpdated[] = [
                    'id' => $product->id,
                    'codigo' => $product->codigo,
                    'nombre' => $product->nombre,
                    'base_cost' => $basePriceFloat,
                    'sale_price' => $calculatedSalePrice,
                    'base' => $base,
                ];
            }
        });

        return [
            'total_selected' => count($productIds),
            'updated' => $updatedCount,
            'skipped_missing_price' => $skippedMissingPrice,
            'skipped_invalid_price' => $skippedInvalidPrice,
            'formula_name' => $formula->getName(),
            'base' => $base,
            'products_updated' => $productsUpdated,
        ];
    }
}
