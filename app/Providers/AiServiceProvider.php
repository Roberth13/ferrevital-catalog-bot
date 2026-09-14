<?php

namespace App\Providers;

use App\Contracts\AiProviderInterface;
use App\Services\Ai\Drivers\GeminiAiDriver;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderInterface::class, function ($app) {
            $provider = config('services.ai.provider', 'gemini');

            return match ($provider) {
                'gemini' => new GeminiAiDriver(
                    apiKey: config('services.ai.gemini.api_key') ?? config('services.gemini.api_key'),
                    model: config('services.ai.gemini.model') ?? config('services.gemini.model', 'gemini-3.6-flash')
                ),
                default => throw new RuntimeException("Proveedor de IA no soportado o no configurado: [{$provider}]"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
