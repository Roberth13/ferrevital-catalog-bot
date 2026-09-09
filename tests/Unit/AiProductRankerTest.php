<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AiProductRanker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class AiProductRankerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Product::unguard();
    }

    public function test_it_returns_empty_for_empty_collection()
    {
        $ranker = new AiProductRanker();
        $this->assertEmpty($ranker->rankByValueForMoney(collect([]), 'test'));
    }

    public function test_it_returns_same_id_for_single_item()
    {
        $ranker = new AiProductRanker();
        $product = new Product(['id' => 10]);
        
        $result = $ranker->rankByValueForMoney(collect([$product]), 'test');
        
        $this->assertEquals([10], $result);
    }

    public function test_it_calls_gemini_and_sorts_ids()
    {
        config(['services.gemini.api_key' => 'fake-key']);
        
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '[2, 3, 1]']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $products = collect([
            new Product(['id' => 1, 'nombre' => 'A', 'precio_divisa' => 100]),
            new Product(['id' => 2, 'nombre' => 'B', 'precio_divisa' => 200]),
            new Product(['id' => 3, 'nombre' => 'C', 'precio_divisa' => 150]),
        ]);

        $ranker = new AiProductRanker();
        $result = $ranker->rankByValueForMoney($products, 'taladro');

        $this->assertEquals([2, 3, 1], $result);
    }
    
    public function test_it_handles_missing_ids_from_ai()
    {
        config(['services.gemini.api_key' => 'fake-key']);
        
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '[3]'] // AI only returned 3, forgot 1 and 2
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $products = collect([
            new Product(['id' => 1, 'nombre' => 'A', 'precio_divisa' => 100]),
            new Product(['id' => 2, 'nombre' => 'B', 'precio_divisa' => 200]),
            new Product(['id' => 3, 'nombre' => 'C', 'precio_divisa' => 150]),
        ]);

        $ranker = new AiProductRanker();
        $result = $ranker->rankByValueForMoney($products, 'taladro');

        // It should append the missing ones: 3 (from AI), then 1, 2
        $this->assertEquals([3, 1, 2], $result);
    }
}
