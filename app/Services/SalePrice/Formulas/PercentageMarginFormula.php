<?php

namespace App\Services\SalePrice\Formulas;

use App\Services\SalePrice\Contracts\SalePriceFormulaInterface;
use InvalidArgumentException;

class PercentageMarginFormula implements SalePriceFormulaInterface
{
    public function getId(): string
    {
        return 'percentage_margin';
    }

    public function getName(): string
    {
        return 'Margen de Ganancia Comercial';
    }

    public function getDescription(): string
    {
        return 'Calcula el precio de venta para obtener un margen de ganancia sobre el precio final: base / (1 - margen / 100).';
    }

    public function getParameterDefinitions(): array
    {
        return [
            'margin' => [
                'label' => 'Margen de Ganancia (%)',
                'type' => 'number',
                'step' => '0.01',
                'min' => 0,
                'max' => 99.99,
                'default' => 25,
                'required' => true,
                'placeholder' => 'Ej: 25',
                'help' => 'Margen porcentual sobre el precio final de venta (0 <= margen < 100).'
            ],
        ];
    }

    public function calculate(float $basePrice, array $parameters): float
    {
        if ($basePrice < 0) {
            throw new InvalidArgumentException("El precio base no puede ser negativo.");
        }

        if (!isset($parameters['margin']) || !is_numeric($parameters['margin'])) {
            throw new InvalidArgumentException("El parámetro 'margin' es requerido y debe ser numérico.");
        }

        $margin = (float) $parameters['margin'];
        if ($margin < 0 || $margin >= 100) {
            throw new InvalidArgumentException("El margen de ganancia debe cumplir la condición: 0 <= margen < 100.");
        }

        $salePrice = $basePrice / (1 - ($margin / 100));

        return round($salePrice, 2);
    }
}
