<?php

namespace Tests\Unit;

use App\Services\Parsers\Support\ProductTextNormalizer;
use PHPUnit\Framework\TestCase;

class ProductTextNormalizerTest extends TestCase
{
    private ProductTextNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ProductTextNormalizer();
    }

    public function test_clean_text_removes_carriage_returns_and_empty_lines(): void
    {
        $input = "Línea 1 \r\n  \r\nLínea 2\r\n  Línea 3  \r\n";
        $expected = "Línea 1\nLínea 2\nLínea 3";

        $this->assertEquals($expected, $this->normalizer->cleanText($input));
    }

    public function test_to_lines_splits_string_into_non_empty_trimmed_lines(): void
    {
        $input = "  Producto A  \n\n   Código 123  \n ";
        $expected = ['Producto A', 'Código 123'];

        $this->assertEquals($expected, $this->normalizer->toLines($input));
    }

    public function test_parse_decimal_handles_various_number_formats(): void
    {
        $this->assertEquals(123.45, $this->normalizer->parseDecimal('123.45'));
        $this->assertEquals(1234.56, $this->normalizer->parseDecimal('1.234,56'));
        $this->assertEquals(1234.56, $this->normalizer->parseDecimal('1234,56'));
        $this->assertEquals(123456.78, $this->normalizer->parseDecimal('123 456.78'));
        $this->assertEquals(45.0, $this->normalizer->parseDecimal('45'));
    }
}
