<?php

namespace Tests\Unit;

use App\Services\Parsers\Support\ProductPatternMatcher;
use App\Services\Parsers\Support\ProductTextNormalizer;
use PHPUnit\Framework\TestCase;

class ProductPatternMatcherTest extends TestCase
{
    private ProductPatternMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ProductPatternMatcher(new ProductTextNormalizer());
    }

    public function test_extract_code_finds_valid_alphanumeric_code(): void
    {
        $text = "TALADRO DE BANCO\nREF: DZJ02-13\n$48.97";
        $this->assertEquals('DZJ02-13', $this->matcher->extractCode($text));
    }

    public function test_extract_price_finds_decimal_price(): void
    {
        $text = "DESCRIPCION\n$ 148,97\nGARANTIA 1 ANO";
        $this->assertEquals(148.97, $this->matcher->extractPrice($text));
    }

    public function test_looks_like_code(): void
    {
        $this->assertTrue($this->matcher->looksLikeCode('DZJ02-13'));
        $this->assertTrue($this->matcher->looksLikeCode('ING-450W'));
        $this->assertFalse($this->matcher->looksLikeCode('TALADRO DE BANCO ROTATIVO'));
        $this->assertFalse($this->matcher->looksLikeCode('12345'));
    }

    public function test_looks_like_price(): void
    {
        $this->assertTrue($this->matcher->looksLikePrice('48,97'));
        $this->assertTrue($this->matcher->looksLikePrice('$48.97'));
        $this->assertTrue($this->matcher->looksLikePrice(' 1234,50 '));
        $this->assertFalse($this->matcher->looksLikePrice('TALADRO 48.97'));
    }

    public function test_looks_like_code_and_price_same_line(): void
    {
        $line = 'DZJ02-13 48.97';
        $isMatch = $this->matcher->looksLikeCodeAndPrice($line, $code, $price);

        $this->assertTrue($isMatch);
        $this->assertEquals('DZJ02-13', $code);
        $this->assertEquals(48.97, $price);
    }

    public function test_extract_code_price_pairs_from_red_text(): void
    {
        $redText = "DZJ02-13\n48,97\nKAP-002 99.50";
        $pairs = $this->matcher->extractCodePricePairs($redText);

        $expected = [
            ['codigo' => 'DZJ02-13', 'precio' => 48.97],
            ['codigo' => 'KAP-002', 'precio' => 99.50],
        ];

        $this->assertEquals($expected, $pairs);
    }

    public function test_find_code_position_exact_and_fuzzy(): void
    {
        $lines = [
            'DESCRIPCION GENERAL',
            'CODIGO DZJ02 13',
            'PRECIO 48.97'
        ];

        $this->assertNull($this->matcher->findCodePosition($lines, 'DZJ02-13'));
        $this->assertEquals(1, $this->matcher->findCodePositionFuzzy($lines, 'DZJ02-13'));
    }
}
