<?php

namespace App\Services\SalePrice;

use App\Services\SalePrice\Contracts\SalePriceFormulaInterface;
use App\Services\SalePrice\Formulas\PercentageMarkupFormula;
use App\Services\SalePrice\Formulas\PercentageMarginFormula;
use App\Services\SalePrice\Formulas\FixedMarkupFormula;
use App\Services\SalePrice\Formulas\DirectMultiplierFormula;
use InvalidArgumentException;

class SalePriceFormulaRegistry
{
    /**
     * @var array<string, SalePriceFormulaInterface>
     */
    protected array $formulas = [];

    public function __construct()
    {
        $this->registerDefaultFormulas();
    }

    protected function registerDefaultFormulas(): void
    {
        $this->register(new PercentageMarkupFormula());
        $this->register(new PercentageMarginFormula());
        $this->register(new FixedMarkupFormula());
        $this->register(new DirectMultiplierFormula());
    }

    public function register(SalePriceFormulaInterface $formula): void
    {
        $this->formulas[$formula->getId()] = $formula;
    }

    /**
     * @return array<string, SalePriceFormulaInterface>
     */
    public function all(): array
    {
        return $this->formulas;
    }

    public function get(string $id): SalePriceFormulaInterface
    {
        if (!isset($this->formulas[$id])) {
            throw new InvalidArgumentException("La fórmula con identificador '{$id}' no está registrada.");
        }

        return $this->formulas[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->formulas[$id]);
    }
}
