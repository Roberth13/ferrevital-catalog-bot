<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Parsers\AiCatalogParser;
use App\Jobs\ParseAiCatalogChunkJob;
use App\Exceptions\CatalogParseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Catalog;
use Illuminate\Support\Facades\DB;

class AiCatalogParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'fake-key']);
        config(['services.gemini.model' => 'gemini-3.6-flash']);
    }

    public function test_it_dispatches_jobs_for_chunks()
    {
        Queue::fake();
        Log::spy();

        $parser = new AiCatalogParser();
        $text = str_repeat("a\n", 30000); // 60k chars -> 2 chunks

        $result = $parser->parse($text, '', 1);

        $this->assertEmpty($result);
        Queue::assertPushed(ParseAiCatalogChunkJob::class, 2);
    }

    public function test_it_returns_empty_when_text_is_empty_without_api_call()
    {
        Queue::fake();

        $parser = new AiCatalogParser();
        $result = $parser->parse('', '');

        $this->assertEmpty($result);
        Queue::assertNothingPushed();
    }

    public function test_job_handles_successful_response()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '[{"codigo":"A1","nombre":"Prod1"}]']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $catalog = Catalog::create([
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'status' => 'processing',
        ]);

        $job = new ParseAiCatalogChunkJob("some text", $catalog->id);
        
        $job->handle(app(\App\Contracts\AiProviderInterface::class));

        $this->assertDatabaseHas('products', [
            'codigo' => 'A1',
            'nombre' => 'Prod1',
            'catalog_id' => $catalog->id
        ]);
    }

    public function test_job_throws_exception_on_429_to_trigger_backoff()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Too many requests'], 429)
        ]);

        $job = new ParseAiCatalogChunkJob("some text", 1);
        
        $this->expectException(\App\Exceptions\Ai\AiRateLimitException::class);

        $job->handle(app(\App\Contracts\AiProviderInterface::class));
    }

    public function test_job_throws_exception_on_503_to_trigger_backoff()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Unavailable'], 503)
        ]);

        $job = new ParseAiCatalogChunkJob("some text", 1);
        
        $this->expectException(\App\Exceptions\Ai\AiTemporaryException::class);

        $job->handle(app(\App\Contracts\AiProviderInterface::class));
    }

    public function test_job_throws_catalog_parse_exception_on_invalid_json()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Not a JSON array']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $job = new ParseAiCatalogChunkJob("some text", 1);
        
        $this->expectException(CatalogParseException::class);

        $job->handle(app(\App\Contracts\AiProviderInterface::class));
    }
    
    public function test_job_throws_catalog_parse_exception_on_invalid_api_response_schema()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response("Not a JSON", 200)
        ]);

        $job = new ParseAiCatalogChunkJob("some text", 1);
        
        $this->expectException(CatalogParseException::class);

        $job->handle(app(\App\Contracts\AiProviderInterface::class));
    }
}
