<?php

namespace App\Services\SalePrice\Formulas;

use App\Services\SalePrice\Contracts\SalePriceFormulaInterface;
use InvalidArgumentException;

class PercentageMarkupFormula implements SalePriceFormulaInterface
{
    public function getId(): string
    {
        return 'percentage_markup';
    }

    public function getName(): string
    {
        return 'Recargo Porcentual sobre Costo (Markup)';
    }

    public function getDescription(): string
    {
        return 'Calcula el precio de venta aplicando un porcentaje de recargo sobre el costo base: base * (1 + porcentaje / 100).';
    }

    public function getParameterDefinitions(): array
    {
        return [
            'percentage' => [
                'label' => 'Porcentaje de Recargo (%)',
                'type' => 'number',
                'step' => '0.01',
                'min' => 0,
                'default' => 30,
                'required' => true,
                'placeholder' => 'Ej: 30',
                'help' => 'Porcentaje que se sumará al costo base (debe ser >= 0).'
            ],
        ];
    }

    public function calculate(float $basePrice, array $parameters): float
    {
        if ($basePrice < 0) {
            throw new InvalidArgumentException("El precio base no puede ser negativo.");
        }

        if (!isset($parameters['percentage']) || !is_numeric($parameters['percentage'])) {
            throw new InvalidArgumentException("El parámetro 'percentage' es requerido y debe ser numérico.");
        }

        $percentage = (float) $parameters['percentage'];
        if ($percentage < 0) {
            throw new InvalidArgumentException("El porcentaje de recargo no puede ser negativo.");
        }

        $salePrice = $basePrice * (1 + ($percentage / 100));

        return round($salePrice, 2);
    }
}
