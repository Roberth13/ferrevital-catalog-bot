<?php

namespace Tests\Unit;

use App\Services\ProductParser;
use PHPUnit\Framework\TestCase;

class ProductParserOcrAssociationTest extends TestCase
{
    private ProductParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProductParser();
    }

    /**
     * Caso 1: SKU presente en normalText + redText.
     */
    public function test_case_1_sku_in_both_normal_and_red_text(): void
    {
        $normalText = "DCPL208 ATORNILLADOR DE IMPACTO\n*POTENCIA 600W\n*BATERIA 20V";
        $redText = "DCPL208\n83,48\nAno de Garantia";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertCount(1, $products);
        $this->assertEquals('DCPL208', $products[0]['codigo']);
        $this->assertEquals(83.48, $products[0]['precio_divisa']);
        $this->assertStringContainsString('600W', $products[0]['descripcion']);
    }

    /**
     * Caso 2: SKU presente únicamente en redText.
     */
    public function test_case_2_sku_only_in_red_text(): void
    {
        $normalText = "IMPACTO INALAMBRICO\n*BATERIA 20 V MAX\n*POTENCIA MAXIMA DE SALIDA 600 W\nQUE INCLUYE 1 BATERIA 20V";
        $redText = "ATORNILLADOR DE\nDCPL208 (TIPO ADM)\nDong Cheng\n83,48\nAno de Garantia";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertNotEmpty($products);
        $this->assertCount(1, $products);
        $this->assertEquals('DCPL208 (TIPO ADM)', $products[0]['codigo']);
        $this->assertEquals(83.48, $products[0]['precio_divisa']);
        $this->assertStringContainsString('600 W', $products[0]['descripcion']);
    }

    /**
     * Caso 3: Precio presente únicamente en redText (código en normalText).
     */
    public function test_case_3_price_only_in_red_text_code_in_normal_text(): void
    {
        $normalText = "DCPB358 LLAVE DE IMPACTO\n*PAR MAXIMO 358 N-M\n*BATERIA 20V";
        $redText = "124,00\nCARACTERISTICAS:\nAno de Garantia";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertCount(1, $products);
        $this->assertEquals('DCPB358', $products[0]['codigo']);
        $this->assertEquals(124.0, $products[0]['precio_divisa']);
        $this->assertStringContainsString('358 N-M', $products[0]['descripcion']);
    }

    /**
     * Caso 4: Múltiples productos en la misma página (con segmentación de bloques).
     */
    public function test_case_4_multiple_products_on_same_page(): void
    {
        $normalText = "TALADRO DE IMPACTO\n*VELOCIDAD 0-2600 RPM\n*BATERIA 20V\nAMOLADORA ANGULAR\n*DISCO 115 MM\n*POTENCIA 850W";
        $redText = "DCPL208 83,48\nDSMO2-115 45,90";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertCount(2, $products);
        $this->assertEquals('DCPL208', $products[0]['codigo']);
        $this->assertEquals(83.48, $products[0]['precio_divisa']);
        $this->assertEquals('DSMO2-115', $products[1]['codigo']);
        $this->assertEquals(45.90, $products[1]['precio_divisa']);
    }

    /**
     * Caso 5: SKU con sufijo de tipo o formato complejo / fragmentado por OCR.
     */
    public function test_case_5_sku_with_type_suffix(): void
    {
        $normalText = "MARTILLO COMBINADO INALAMBRICO\n*ENERGIA DE IMPACTO 2.8J\n*MOTOR SIN ESCOBILLAS";
        $redText = "DCKIT27 (TIPO EK)\n221,13\nAno de Garantia";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertCount(1, $products);
        $this->assertEquals('DCKIT27 (TIPO EK)', $products[0]['codigo']);
        $this->assertEquals(221.13, $products[0]['precio_divisa']);
    }

    /**
     * Caso 6: Página sin productos (portada o texto institucional sin código ni precio).
     */
    public function test_case_6_page_without_products_returns_empty_array(): void
    {
        $normalText = "DONGCHENG POWER TOOLS CATALOG 2026\nPROFESSIONAL POWER TOOLS\nWWW.DONGCHENG.COM";
        $redText = "Dong Cheng Power Tools\nAño de Garantía";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertEmpty($products);
    }

    /**
     * Caso 7: Preservación del comportamiento anterior de OCR (regresión cero).
     */
    public function test_case_7_backward_compatibility_with_existing_ocr_fixtures(): void
    {
        $normalText = "TALADRO DE IMPACTO\nDCPL208\n*POTENCIA 600W\n*BATERIA 20V 2A";
        $redText = "DCPL208\n83,48\n1 Ano de Garantia";

        $products = $this->parser->parseMultipleOcr($normalText, $redText);

        $this->assertCount(1, $products);
        $this->assertEquals('DCPL208', $products[0]['codigo']);
        $this->assertEquals(83.48, $products[0]['precio_divisa']);
        $this->assertStringContainsString('600W', $products[0]['descripcion']);
        $this->assertStringContainsString('Garantia', $products[0]['garantia']);
    }
}
