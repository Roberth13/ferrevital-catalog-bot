<?php

namespace App\Services\SalePrice\Formulas;

use App\Services\SalePrice\Contracts\SalePriceFormulaInterface;
use InvalidArgumentException;

class FixedMarkupFormula implements SalePriceFormulaInterface
{
    public function getId(): string
    {
        return 'fixed_markup';
    }

    public function getName(): string
    {
        return 'Incremento Fijo sobre Costo';
    }

    public function getDescription(): string
    {
        return 'Calcula el precio de venta sumando un monto fijo directo al costo base: base + monto.';
    }

    public function getParameterDefinitions(): array
    {
        return [
            'amount' => [
                'label' => 'Monto Fijo a Incrementar',
                'type' => 'number',
                'step' => '0.01',
                'min' => 0,
                'default' => 5,
                'required' => true,
                'placeholder' => 'Ej: 5.00',
                'help' => 'Monto fijo no negativo que se sumará al precio base.'
            ],
        ];
    }

    public function calculate(float $basePrice, array $parameters): float
    {
        if ($basePrice < 0) {
            throw new InvalidArgumentException("El precio base no puede ser negativo.");
        }

        if (!isset($parameters['amount']) || !is_numeric($parameters['amount'])) {
            throw new InvalidArgumentException("El parámetro 'amount' es requerido y debe ser numérico.");
        }

        $amount = (float) $parameters['amount'];
        if ($amount < 0) {
            throw new InvalidArgumentException("El monto de incremento fijo no puede ser negativo.");
        }

        $salePrice = $basePrice + $amount;

        return round($salePrice, 2);
    }
}
