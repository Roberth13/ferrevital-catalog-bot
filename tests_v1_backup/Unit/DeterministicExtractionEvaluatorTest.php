<?php

namespace Tests\Unit;

use App\Services\Parsers\Support\DeterministicExtractionEvaluator;
use App\Services\Parsers\Support\ProductPatternMatcher;
use PHPUnit\Framework\TestCase;

class DeterministicExtractionEvaluatorTest extends TestCase
{
    private DeterministicExtractionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new DeterministicExtractionEvaluator();
    }

    /**
     * Caso 1: Resultado determinístico completo con productos válidos -> SUFFICIENT.
     */
    public function test_complete_deterministic_result_is_sufficient(): void
    {
        $products = [
            [
                'codigo' => 'JDHK2292',
                'nombre' => 'Juego de Llaves Allen 9 Pza',
                'precio_divisa' => 5.14,
                'precio_bs' => null,
            ]
        ];

        $normalText = "Juego de Llaves Allen 9 Pza\nJDHK2292\n$ 5.14\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        $this->assertEquals(DeterministicExtractionEvaluator::SUFFICIENT, $result);
    }

    /**
     * Caso 2: 0 productos + página informativa/portada sin precios ni specs -> NO_PRODUCT_PAGE.
     */
    public function test_zero_products_and_informational_page_is_no_product_page(): void
    {
        $products = [];
        $normalText = "AUTHORIZED DISTRIBUTOR\nJADEVER CORP, C.A\nis appointed as authorized distributor in Venezuela.\nValid from Jan.1st 2026 to Dec.31st 2026.\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        $this->assertEquals(DeterministicExtractionEvaluator::NO_PRODUCT_PAGE, $result);
    }

    /**
     * Caso 3: 0 productos + señales de producto (precios con $) -> INSUFFICIENT (fallback IA).
     */
    public function test_zero_products_with_product_and_price_signals_is_insufficient(): void
    {
        $products = [];
        $normalText = "TALADRO DE IMPACTO 710W\nConsulte con su asesor comercial.\nPrecio de oferta: $ 45.00\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        $this->assertEquals(DeterministicExtractionEvaluator::INSUFFICIENT, $result);
    }

    /**
     * Caso 4: Código detectado pero sin precio -> INSUFFICIENT si hay señales o NO_PRODUCT_PAGE si no hay precio.
     */
    public function test_code_without_price_is_filtered_and_evaluated(): void
    {
        // Producto sin precio (precio = null)
        $products = [
            [
                'codigo' => 'DZJ02-13',
                'nombre' => 'Taladro de Impacto 500W',
                'precio_divisa' => null,
                'precio_bs' => null,
            ]
        ];

        // Texto con especificaciones técnicas densas
        $normalText = "TALADRO DE IMPACTO DZJ02-13\n*POTENCIA NOMINAL: 500W\n*VELOCIDAD SIN CARGA: 2600 RPM\n*VOLTAJE: 110V\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        // Debido a que tiene especificaciones técnicas pero ningún producto tiene precio, requiere IA para buscar precio
        $this->assertEquals(DeterministicExtractionEvaluator::INSUFFICIENT, $result);
    }

    /**
     * Caso 5: Precio detectado pero sin código asociado -> INSUFFICIENT.
     */
    public function test_price_without_code_triggers_insufficient(): void
    {
        $products = [];
        $normalText = "Amoladora angular profesional 115mm\nPotente motor industrial.\nPrecio especial: $ 72.10\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        $this->assertEquals(DeterministicExtractionEvaluator::INSUFFICIENT, $result);
    }

    /**
     * Caso 6: Producto parcialmente construido (SKU numérico inválido o nombre vacío) -> INSUFFICIENT si hay precios.
     */
    public function test_partially_constructed_invalid_product_is_filtered(): void
    {
        $products = [
            [
                'codigo' => '100.00', // SKU inválido (falso positivo numérico)
                'nombre' => '',
                'precio_divisa' => 4.08,
            ]
        ];

        $normalText = "Tabla de empaque unitario Unit Uni/Min 100.00 $ 4.08\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        // Al ser SKU numérico y nombre vacío, se descarta y al tener $, se marca como INSUFFICIENT
        $this->assertEquals(DeterministicExtractionEvaluator::INSUFFICIENT, $result);
    }

    /**
     * Caso 7: Múltiples productos correctamente extraídos -> SUFFICIENT.
     */
    public function test_multiple_valid_products_is_sufficient(): void
    {
        $products = [
            [
                'codigo' => 'DZJ02-13',
                'nombre' => 'Taladro de Impacto 500W',
                'precio_divisa' => 48.98,
            ],
            [
                'codigo' => 'DZJI6',
                'nombre' => 'Taladro de Impacto 710W',
                'precio_divisa' => 70.0,
            ]
        ];

        $normalText = "DZJ02-13 Taladro 500W $ 48.98\nDZJI6 Taladro 710W $ 70.00\n";
        $result = $this->evaluator->evaluate($products, $normalText);

        $this->assertEquals(DeterministicExtractionEvaluator::SUFFICIENT, $result);
    }

    /**
     * Caso 8: OCR normal/red correctamente asociado -> SUFFICIENT.
     */
    public function test_ocr_normal_and_red_properly_associated_is_sufficient(): void
    {
        $products = [
            [
                'codigo' => 'DCPL208 (TIPO EM)',
                'nombre' => 'Rotomartillo Impacto Inalambrico 20V',
                'precio_divisa' => 128.63,
            ]
        ];

        $normalText = "ROTOMARTILLO IMPACTO INALAMBRICO\n*BATERIA 20V\n*POTENCIA MAXIMA 600W\n";
        $redText = "DCPL208 (TIPO EM)\n128,63\nAño de Garantia\n";

        $result = $this->evaluator->evaluate($products, $normalText, $redText);

        $this->assertEquals(DeterministicExtractionEvaluator::SUFFICIENT, $result);
    }

    /**
     * Caso 9: OCR ambiguo (precios en capa roja pero 0 productos asociados) -> INSUFFICIENT.
     */
    public function test_ocr_ambiguous_with_prices_in_red_layer_is_insufficient(): void
    {
        $products = []; // Falló la asociación determinística
        $normalText = "REF: = E *POTENCIA NOMINAL DE ENTRADA: 850 W *VELOCIDAD SIN CARGA: 11800 RPM\n";
        $redText = "35,73 75,63 Afiode Garantia\n";

        $result = $this->evaluator->evaluate($products, $normalText, $redText);

        $this->assertEquals(DeterministicExtractionEvaluator::INSUFFICIENT, $result);
    }

    /**
     * Caso 10: Texto completamente vacío o nulo -> NO_PRODUCT_PAGE.
     */
    public function test_empty_text_is_no_product_page(): void
    {
        $result = $this->evaluator->evaluate([], "", "");
        $this->assertEquals(DeterministicExtractionEvaluator::NO_PRODUCT_PAGE, $result);
    }
}
