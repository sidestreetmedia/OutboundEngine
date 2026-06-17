<?php

namespace App\Providers;

use App\Contracts\EmailVerifier;
use App\Contracts\LlmClient;
use App\Contracts\OutboundProvider;
use App\Services\Cost\CostMeter;
use App\Services\Ingestion\Extractors\DocxExtractor;
use App\Services\Ingestion\Extractors\HtmlExtractor;
use App\Services\Ingestion\Extractors\PdfExtractor;
use App\Services\Ingestion\Extractors\PlainTextExtractor;
use App\Services\Ingestion\HtmlToText;
use App\Services\Ingestion\TextExtractionManager;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\NullLlmClient;
use App\Services\Outbound\InstantlyProvider;
use App\Services\Outbound\LemlistProvider;
use App\Services\Outbound\OutboundManager;
use App\Services\Verification\NullVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * Wires OutboundEngine's services into the container.
 *
 * Phase 1 binds the real CostMeter plus null/stub implementations of the LLM,
 * verifier, and sending contracts. Later phases swap individual bindings
 * (Anthropic in Phase 2, a real verifier in Phase 3, live providers in Phase 6)
 * without touching the code that depends on the contracts.
 */
class OutboundServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cost meter — the one fully-real service in Phase 1.
        $this->app->singleton(CostMeter::class);

        // LLM: the real Anthropic client once a key is set, else the null stub
        // so the app still boots without credentials.
        $this->app->bind(LlmClient::class, function ($app): LlmClient {
            $llm = config('outbound.llm');

            if (blank($llm['key'] ?? null)) {
                return new NullLlmClient();
            }

            return new AnthropicClient(
                $app->make(CostMeter::class),
                $llm['key'],
                $llm['model'] ?? 'claude-sonnet-4-6',
            );
        });

        // Verifier: stub until Phase 3.
        $this->app->bind(EmailVerifier::class, NullVerifier::class);

        // Sending platforms: both built from config, behind one manager.
        $this->app->singleton(OutboundManager::class, function (): OutboundManager {
            $config = config('outbound');

            $providers = [
                'instantly' => new InstantlyProvider($config['providers']['instantly']['key'] ?? null),
                'lemlist' => new LemlistProvider($config['providers']['lemlist']['key'] ?? null),
            ];

            return new OutboundManager($providers, $config['provider'] ?? 'instantly');
        });

        // Resolving the bare contract gives back the active (default) provider.
        $this->app->bind(OutboundProvider::class, function ($app): OutboundProvider {
            return $app->make(OutboundManager::class)->driver();
        });

        // Ingestion: the file text extractors behind one manager. IngestionService
        // depends on this and is auto-resolved by the container.
        $this->app->singleton(HtmlToText::class);

        $this->app->singleton(TextExtractionManager::class, function ($app): TextExtractionManager {
            return new TextExtractionManager([
                new PlainTextExtractor(),
                new PdfExtractor(),
                new DocxExtractor(),
                new HtmlExtractor($app->make(HtmlToText::class)),
            ]);
        });
    }
}
