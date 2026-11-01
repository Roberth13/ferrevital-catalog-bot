<?php

namespace Tests\Unit;

use App\Services\Parsers\Support\ProductBlockSegmenter;
use App\Services\Parsers\Support\ProductPatternMatcher;
use PHPUnit\Framework\TestCase;

class ProductBlockSegmenterTest extends TestCase
{
    private ProductBlockSegmenter $segmenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->segmenter = new ProductBlockSegmenter(new ProductPatternMatcher());
    }

    public function test_single_product_block_segmentation(): void
    {
        $lines = [
            'TALADRO DE BANCO',
            'VELOCIDAD VARIABLE 2800 RPM',
            'DZJ02-13',
            '* INCLUYE MALETIN PLASTICO',
            'GARANTIA 1 ANO',
        ];

        $block = $this->segmenter->extractBlockForCode($lines, 'DZJ02-13', null);

        $this->assertCount(5, $block);
        $this->assertEquals('TALADRO DE BANCO', $block[0]);
        $this->assertEquals('GARANTIA 1 ANO', end($block));
    }

    public function test_multiple_products_block_segmentation_with_subsequent_code(): void
    {
        $lines = [
            'TALADRO ROTATIVO PROFESIONAL',
            'VELOCIDAD VARIABLE 2800 RPM',
            'DZJ02-13',
            'AMOLADORA ANGULAR INDUSTRIAL',
            'POTENCIA MOTOR 850W',
            'KAP-002',
            '* DISCO DE CORTE 4 1/2 INCH',
        ];

        $block1 = $this->segmenter->extractBlockForCode($lines, 'DZJ02-13', 'KAP-002', ['DZJ02-13', 'KAP-002']);
        $block2 = $this->segmenter->extractBlockForCode($lines, 'KAP-002', null, ['DZJ02-13', 'KAP-002']);

        $this->assertCount(3, $block1);
        $this->assertEquals('TALADRO ROTATIVO PROFESIONAL', $block1[0]);
        $this->assertEquals('DZJ02-13', end($block1));

        $this->assertCount(4, $block2);
        $this->assertEquals('AMOLADORA ANGULAR INDUSTRIAL', $block2[0]);
        $this->assertEquals('* DISCO DE CORTE 4 1/2 INCH', end($block2));
    }

    public function test_code_immediately_followed_by_another_code(): void
    {
        $lines = [
            'PRODUCTO DE PRUEBA A',
            'DZJ01-10',
            'DZJ02-13',
            'PRODUCTO DE PRUEBA C',
        ];

        $block1 = $this->segmenter->extractBlockForCode($lines, 'DZJ01-10', 'DZJ02-13', ['DZJ01-10', 'DZJ02-13']);

        $this->assertCount(2, $block1);
        $this->assertEquals('PRODUCTO DE PRUEBA A', $block1[0]);
        $this->assertEquals('DZJ01-10', $block1[1]);
    }

    public function test_detects_spec_lines_and_ignored_lines(): void
    {
        $this->assertTrue($this->segmenter->looksLikeSpecLine('* POTENCIA 500W'));
        $this->assertTrue($this->segmenter->looksLikeSpecLine('VELOCIDAD 2800 RPM'));
        $this->assertTrue($this->segmenter->looksLikeSpecLine('CAPACIDAD 13 MM'));
        $this->assertFalse($this->segmenter->looksLikeSpecLine('TALADRO DE BANCO'));

        $this->assertTrue($this->segmenter->isIgnoredLine('www.ferrevital.com'));
        $this->assertTrue($this->segmenter->isIgnoredLine('Unit Uni/Min Uni/Pack Precio'));
        $this->assertFalse($this->segmenter->isIgnoredLine('TALADRO PERFORADOR'));
    }

    public function test_ocr_fragmented_lines_with_fuzzy_matching(): void
    {
        $lines = [
            'TALADRO DE IMPACTO',
            'DZJ02 13', // OCR space instead of hyphen
            'PRECIO DE VENTA 48.97',
        ];

        $block = $this->segmenter->extractBlockForCode($lines, 'DZJ02-13', null);

        $this->assertCount(3, $block);
        $this->assertEquals('TALADRO DE IMPACTO', $block[0]);
    }
}
