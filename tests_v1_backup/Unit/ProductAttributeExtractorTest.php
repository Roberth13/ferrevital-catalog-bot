<?php

namespace Tests\Unit;

use App\Services\Parsers\Support\ProductAttributeExtractor;
use App\Services\Parsers\Support\ProductBlockSegmenter;
use App\Services\Parsers\Support\ProductTextNormalizer;
use PHPUnit\Framework\TestCase;

class ProductAttributeExtractorTest extends TestCase
{
    private ProductAttributeExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ProductAttributeExtractor(
            new ProductBlockSegmenter(),
            new ProductTextNormalizer()
        );
    }

    public function test_extract_name_from_block(): void
    {
        $block = [
            'TALADRO DE BANCO ROTATIVO',
            'DZJ02-13',
            '* POTENCIA 500W',
        ];

        $name = $this->extractor->extractNameFromBlock($block, 'DZJ02-13');
        $this->assertEquals('TALADRO DE BANCO ROTATIVO', $name);
    }

    public function test_looks_like_name(): void
    {
        $this->assertTrue($this->extractor->looksLikeName('TALADRO DE BANCO'));
        $this->assertTrue($this->extractor->looksLikeName('AMOLADORA ANGULAR'));
        $this->assertFalse($this->extractor->looksLikeName('12345'));
        $this->assertFalse($this->extractor->looksLikeName('ab'));
    }

    public function test_extract_description_from_block(): void
    {
        $block = [
            'TALADRO DE BANCO',
            'POTENCIA 500W',
            'VELOCIDAD 2800 RPM',
            '* INCLUYE MANDRIL',
        ];

        $desc = $this->extractor->extractDescriptionFromBlock($block);
        $this->assertStringContainsString('POTENCIA 500W', $desc);
        $this->assertStringContainsString('VELOCIDAD 2800 RPM', $desc);
        $this->assertStringContainsString('* INCLUYE MANDRIL', $desc);
    }

    public function test_extract_warranty_condiciones_entrega_and_price_from_block(): void
    {
        $block = [
            'TALADRO DE BANCO',
            'GARANTIA DE 1 ANO',
            'CONDICIONES PAGO CONTADO',
            'DESPACHO EN 2 DIAS',
            'PRECIO 48.97',
        ];

        $this->assertEquals('GARANTIA DE 1 ANO', $this->extractor->extractWarrantyFromBlock($block));
        $this->assertEquals('CONDICIONES PAGO CONTADO', $this->extractor->extractCondicionesFromBlock($block));
        $this->assertEquals('DESPACHO EN 2 DIAS', $this->extractor->extractEntregaFromBlock($block));
        $this->assertEquals(48.97, $this->extractor->extractPriceFromBlock($block));
    }

    public function test_extract_standalone_name_description_and_warranty(): void
    {
        $redText = "TALADRO DE BANCO\nDZJ02-13";
        $normalText = "TALADRO DE BANCO ROTATIVO\nGARANTIA 1 ANO";

        $name = $this->extractor->extractName($redText, $normalText, 'DZJ02-13');
        $this->assertEquals('TALADRO DE BANCO', $name);

        $descText = "* POTENCIA 500W\n* INCLUYE MANDRIL";
        $desc = $this->extractor->extractDescription($descText);
        $this->assertStringContainsString('* POTENCIA 500W', $desc);

        $warranty = $this->extractor->extractWarranty($normalText);
        $this->assertEquals('GARANTIA 1 ANO', $warranty);
    }
}
