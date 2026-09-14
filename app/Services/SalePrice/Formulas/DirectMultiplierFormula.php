<?php

namespace App\Services\SalePrice\Formulas;

use App\Services\SalePrice\Contracts\SalePriceFormulaInterface;
use InvalidArgumentException;

class DirectMultiplierFormula implements SalePriceFormulaInterface
{
    public function getId(): string
    {
        return 'direct_multiplier';
    }

    public function getName(): string
    {
        return 'Factor Multiplicador Directo';
    }

    public function getDescription(): string
    {
        return 'Calcula el precio de venta multiplicando el costo base por un factor: base * multiplicador.';
    }

    public function getParameterDefinitions(): array
    {
        return [
            'multiplier' => [
                'label' => 'Factor Multiplicador',
                'type' => 'number',
                'step' => '0.01',
                'min' => 0.01,
                'default' => 1.35,
                'required' => true,
                'placeholder' => 'Ej: 1.35',
                'help' => 'Factor de multiplicación directo (debe ser estrictamente mayor que 0).'
            ],
        ];
    }

    public function calculate(float $basePrice, array $parameters): float
    {
        if ($basePrice < 0) {
            throw new InvalidArgumentException("El precio base no puede ser negativo.");
        }

        if (!isset($parameters['multiplier']) || !is_numeric($parameters['multiplier'])) {
            throw new InvalidArgumentException("El parámetro 'multiplier' es requerido y debe ser numérico.");
        }

        $multiplier = (float) $parameters['multiplier'];
        if ($multiplier <= 0) {
            throw new InvalidArgumentException("El factor multiplicador debe ser estrictamente mayor que 0.");
        }

        $salePrice = $basePrice * $multiplier;

        return round($salePrice, 2);
    }
}
