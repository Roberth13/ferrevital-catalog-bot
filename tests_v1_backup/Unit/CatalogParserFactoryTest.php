<?php

namespace Tests\Unit;

use App\Services\Parsers\CatalogParserFactory;
use App\Services\Parsers\DongChengParser;
use App\Services\Parsers\DylluParser;
use App\Services\Parsers\IngcoParser;
use App\Services\Parsers\JadeverParser;
use App\Services\Parsers\PromakerParser;
use App\Services\Parsers\RonixParser;
use App\Services\Parsers\VertParser;
use App\Services\Parsers\WadfowParser;
use App\Services\ProductParser;
use Tests\TestCase;

class CatalogParserFactoryTest extends TestCase
{
    public function test_factory_resolves_promaker_parser(): void
    {
        $parser = CatalogParserFactory::make("CATALOGO PROMAKER 2026\nTaladro 20V");
        $this->assertInstanceOf(PromakerParser::class, $parser);
    }

    public function test_factory_resolves_ingco_parser(): void
    {
        $parser = CatalogParserFactory::make("INGCO TOOLS\nAmoladora 115mm");
        $this->assertInstanceOf(IngcoParser::class, $parser);
    }

    public function test_factory_resolves_jadever_parser(): void
    {
        $parser = CatalogParserFactory::make("JADEVER HARDWARE TOOLS\nEsmeril");
        $this->assertInstanceOf(JadeverParser::class, $parser);
    }

    public function test_factory_resolves_wadfow_parser(): void
    {
        $parser = CatalogParserFactory::make("WADFOW TOOLS\nMartillo");
        $this->assertInstanceOf(WadfowParser::class, $parser);
    }

    public function test_factory_resolves_vert_parser(): void
    {
        $parser = CatalogParserFactory::make("VERT HERRAMIENTAS\nDestornillador");
        $this->assertInstanceOf(VertParser::class, $parser);
    }

    public function test_factory_resolves_dyllu_parser(): void
    {
        $parser = CatalogParserFactory::make("DYLLU TOOLS\nTaladro Percutor");
        $this->assertInstanceOf(DylluParser::class, $parser);
    }

    public function test_factory_resolves_dongcheng_parser(): void
    {
        $parser = CatalogParserFactory::make("DONG CHENG POWER TOOLS\nRotomartillo");
        $this->assertInstanceOf(DongChengParser::class, $parser);

        $parser2 = CatalogParserFactory::make("DONGCHENG PROFESIONAL");
        $this->assertInstanceOf(DongChengParser::class, $parser2);
    }

    public function test_factory_resolves_ronix_parser(): void
    {
        $parser = CatalogParserFactory::make("RONIX PREMIUM TOOLS");
        $this->assertInstanceOf(RonixParser::class, $parser);
    }

    public function test_factory_falls_back_to_product_parser_for_unknown_text(): void
    {
        $parser = CatalogParserFactory::make("GENERIC HARDWARE CATALOG\nHerramientas varias");
        $this->assertInstanceOf(ProductParser::class, $parser);
    }
}
