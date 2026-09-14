<?php

namespace App\Services\SalePrice\Contracts;

interface SalePriceFormulaInterface
{
    /**
     * Unique identifier for the formula (e.g. 'percentage_markup').
     */
    public function getId(): string;

    /**
     * Human-readable name.
     */
    public function getName(): string;

    /**
     * Detailed description of the mathematical calculation.
     */
    public function getDescription(): string;

    /**
     * Defines parameter definitions required by the formula.
     * Example:
     * [
     *     'percentage' => [
     *         'label' => 'Porcentaje de Recargo (%)',
     *         'type' => 'number',
     *         'step' => '0.01',
     *         'min' => 0,
     *         'default' => 20,
     *         'required' => true,
     *     ]
     * ]
     */
    public function getParameterDefinitions(): array;

    /**
     * Calculates the sale price based on base price and parameters.
     *
     * @param float $basePrice
     * @param array $parameters
     * @return float
     * @throws \InvalidArgumentException
     */
    public function calculate(float $basePrice, array $parameters): float;
}
