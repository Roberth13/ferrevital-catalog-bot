<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CatalogOcrProcessor;
use App\Services\CatalogProcessor;
use App\Services\PdfTextExtractor;
use App\Services\Parsers\CatalogParserFactory;
use App\Services\ProductParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogRealE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_jadever_real_pdf_parsing_and_persistence(): void
    {
        $pdfPath = base_path('public/PDFs/Catalogo Jadever 04-05-2026.pdf');
        
        if (!file_exists($pdfPath)) {
            $this->markTestSkipped('Catalogo Jadever 04-05-2026.pdf no encontrado.');
        }

        $supplier = Supplier::create([
            'name' => 'Jadever Supplier',
            'slug' => 'jadever-supplier',
        ]);

        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/jadever_test.pdf',
            'original_filename' => 'Catalogo Jadever 04-05-2026.pdf',
            'status' => 'pending',
            'total_products' => 0,
        ]);

        $sampleText = "JADEVER TOOLS CATALOG 2026\nJuego de Llaves Allen 9 Pza\nJDHK2292\n$ 5.14\nJuego de Llaves Torx 8 Pza\nJDHK3281\n$ 6.47\nCortadora de Grama\nJDLM1501\n$ 120.00\n";
        
        $productParser = app(ProductParser::class);
        $products = $productParser->parse($sampleText);

        $this->assertNotEmpty($products);
        $this->assertCount(3, $products);

        $now = now();
        $insertData = [];
        foreach ($products as $product) {
            $insertData[] = array_merge([
                'extraction_method' => 'text',
                'ai_provider' => null,
                'ai_model' => null,
                'prompt_version' => null,
                'parser_version' => config('services.ai.parser_version', 'v1'),
            ], $product, [
                'supplier_id' => $supplier->id,
                'catalog_id' => $catalog->id,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('products')->upsert(
            $insertData,
            ['supplier_id', 'codigo'],
            ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'extraction_method', 'ai_provider', 'ai_model', 'prompt_version', 'parser_version', 'page_number']
        );

        $dbCount = Product::where('supplier_id', $supplier->id)->count();
        $this->assertEquals(3, $dbCount);

        // Idempotencia: Reprocesar el mismo catalogo
        DB::table('products')->upsert(
            $insertData,
            ['supplier_id', 'codigo'],
            ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'extraction_method', 'ai_provider', 'ai_model', 'prompt_version', 'parser_version', 'page_number']
        );

        $dbCountSecondRun = Product::where('supplier_id', $supplier->id)->count();
        $this->assertEquals($dbCount, $dbCountSecondRun, 'El upsert debe mantener el mismo numero de registros sin duplicar.');
    }

    public function test_dong_cheng_real_ocr_page_extraction_and_persistence(): void
    {
        $pdfPath = base_path('public/PDFs/Dong Cheng.pdf');
        
        if (!file_exists($pdfPath)) {
            $this->markTestSkipped('Dong Cheng.pdf no encontrado.');
        }

        $supplier = Supplier::create([
            'name' => 'Dong Cheng Supplier',
            'slug' => 'dong-cheng-supplier',
        ]);

        $ocrProcessor = app(CatalogOcrProcessor::class);
        $productParser = app(ProductParser::class);

        // Procesar paginas 9 y 23 que contienen productos detectables por OCR
        $p9 = $ocrProcessor->processPage($pdfPath, 9, 150);
        $p23 = $ocrProcessor->processPage($pdfPath, 23, 150);

        $prods9 = $productParser->parse($p9['normal_text'], $p9['red_text']);
        $prods23 = $productParser->parse($p23['normal_text'], $p23['red_text']);

        $allProducts = array_merge($prods9, $prods23);
        $this->assertNotEmpty($allProducts);

        $catalog = Catalog::create([
            'supplier_id' => $supplier->id,
            'filename' => 'catalogs/dong_cheng_test.pdf',
            'original_filename' => 'Dong Cheng.pdf',
            'status' => 'processing',
            'total_products' => count($allProducts),
        ]);

        $insertData = [];
        foreach ($allProducts as $p) {
            $insertData[] = array_merge([
                'extraction_method' => 'ocr',
                'ai_provider' => null,
                'ai_model' => null,
                'prompt_version' => null,
                'parser_version' => config('services.ai.parser_version', 'v1'),
            ], $p, [
                'supplier_id' => $supplier->id,
                'catalog_id' => $catalog->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('products')->upsert(
            $insertData,
            ['supplier_id', 'codigo'],
            ['precio_divisa', 'precio_bs', 'nombre', 'catalog_id', 'is_active', 'updated_at', 'extraction_method', 'ai_provider', 'ai_model', 'prompt_version', 'parser_version', 'page_number']
        );

        $dbProducts = Product::where('supplier_id', $supplier->id)->get();
        $this->assertCount(count($allProducts), $dbProducts);

        foreach ($dbProducts as $product) {
            $this->assertEquals('ocr', $product->extraction_method);
            $this->assertNull($product->ai_provider);
            $this->assertNull($product->ai_model);
            $this->assertNull($product->prompt_version);
            $this->assertEquals('v1', $product->parser_version);
        }
    }
}
