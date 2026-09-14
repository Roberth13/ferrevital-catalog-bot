<?php

namespace Tests\Unit;

use App\Services\SalePrice\Formulas\DirectMultiplierFormula;
use App\Services\SalePrice\Formulas\FixedMarkupFormula;
use App\Services\SalePrice\Formulas\PercentageMarginFormula;
use App\Services\SalePrice\Formulas\PercentageMarkupFormula;
use App\Services\SalePrice\SalePriceFormulaRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SalePriceFormulaTest extends TestCase
{
    public function test_percentage_markup_formula_calculates_correctly(): void
    {
        $formula = new PercentageMarkupFormula();
        $this->assertEquals('percentage_markup', $formula->getId());

        // 100 + 30% markup = 130.00
        $result = $formula->calculate(100.0, ['percentage' => 30]);
        $this->assertEquals(130.00, $result);

        // 7.84 + 15.5% markup = 7.84 * 1.155 = 9.0552 -> 9.06
        $result2 = $formula->calculate(7.84, ['percentage' => 15.5]);
        $this->assertEquals(9.06, $result2);

        // 0% markup = 100.00
        $resultZero = $formula->calculate(100.0, ['percentage' => 0]);
        $this->assertEquals(100.00, $resultZero);
    }

    public function test_percentage_markup_formula_rejects_negative_percentage_or_base(): void
    {
        $formula = new PercentageMarkupFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, ['percentage' => -10]);
    }

    public function test_percentage_markup_formula_rejects_missing_parameter(): void
    {
        $formula = new PercentageMarkupFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, []);
    }

    public function test_percentage_margin_formula_calculates_correctly(): void
    {
        $formula = new PercentageMarginFormula();
        $this->assertEquals('percentage_margin', $formula->getId());

        // 100 / (1 - 0.25) = 100 / 0.75 = 133.33
        $result = $formula->calculate(100.0, ['margin' => 25]);
        $this->assertEquals(133.33, $result);

        // 7.84 / (1 - 0.20) = 7.84 / 0.80 = 9.80
        $result2 = $formula->calculate(7.84, ['margin' => 20]);
        $this->assertEquals(9.80, $result2);

        // 0% margin = 100.00
        $resultZero = $formula->calculate(100.0, ['margin' => 0]);
        $this->assertEquals(100.00, $resultZero);
    }

    public function test_percentage_margin_formula_rejects_margin_less_than_zero(): void
    {
        $formula = new PercentageMarginFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, ['margin' => -5]);
    }

    public function test_percentage_margin_formula_rejects_margin_greater_equal_100(): void
    {
        $formula = new PercentageMarginFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, ['margin' => 100]);
    }

    public function test_fixed_markup_formula_calculates_correctly(): void
    {
        $formula = new FixedMarkupFormula();
        $this->assertEquals('fixed_markup', $formula->getId());

        // 100 + 15.50 = 115.50
        $result = $formula->calculate(100.0, ['amount' => 15.50]);
        $this->assertEquals(115.50, $result);

        // 7.84 + 2.16 = 10.00
        $result2 = $formula->calculate(7.84, ['amount' => 2.16]);
        $this->assertEquals(10.00, $result2);
    }

    public function test_fixed_markup_formula_rejects_negative_amount(): void
    {
        $formula = new FixedMarkupFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, ['amount' => -2.5]);
    }

    public function test_direct_multiplier_formula_calculates_correctly(): void
    {
        $formula = new DirectMultiplierFormula();
        $this->assertEquals('direct_multiplier', $formula->getId());

        // 100 * 1.35 = 135.00
        $result = $formula->calculate(100.0, ['multiplier' => 1.35]);
        $this->assertEquals(135.00, $result);

        // 7.84 * 1.5 = 11.76
        $result2 = $formula->calculate(7.84, ['multiplier' => 1.5]);
        $this->assertEquals(11.76, $result2);
    }

    public function test_direct_multiplier_formula_rejects_multiplier_less_or_equal_to_zero(): void
    {
        $formula = new DirectMultiplierFormula();

        $this->expectException(InvalidArgumentException::class);
        $formula->calculate(100.0, ['multiplier' => 0]);
    }

    public function test_registry_contains_default_formulas_and_retrieves_by_id(): void
    {
        $registry = new SalePriceFormulaRegistry();

        $this->assertTrue($registry->has('percentage_markup'));
        $this->assertTrue($registry->has('percentage_margin'));
        $this->assertTrue($registry->has('fixed_markup'));
        $this->assertTrue($registry->has('direct_multiplier'));

        $this->assertInstanceOf(PercentageMarkupFormula::class, $registry->get('percentage_markup'));
        $this->assertInstanceOf(PercentageMarginFormula::class, $registry->get('percentage_margin'));
        $this->assertInstanceOf(FixedMarkupFormula::class, $registry->get('fixed_markup'));
        $this->assertInstanceOf(DirectMultiplierFormula::class, $registry->get('direct_multiplier'));

        $this->assertCount(4, $registry->all());
    }

    public function test_registry_throws_on_unknown_formula_id(): void
    {
        $registry = new SalePriceFormulaRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->get('non_existent_formula');
    }
}
