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
use App\Services\Settings\Settings;
use App\Services\Verification\MxVerifier;
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

        // Runtime settings: values saved via the settings page/CLI override .env.
        $this->app->singleton(Settings::class);

        // LLM: the real Anthropic client once a key is available (saved setting
        // or env), else the null stub so the app still boots without credentials.
        $this->app->bind(LlmClient::class, function ($app): LlmClient {
            $settings = $app->make(Settings::class);
            $key = $settings->resolve('anthropic_api_key');

            if (blank($key)) {
                return new NullLlmClient();
            }

            return new AnthropicClient(
                $app->make(CostMeter::class),
                $key,
                $settings->resolve('llm_model') ?: 'claude-sonnet-4-6',
            );
        });

        // Verifier: free MX/syntax verification by default (no spend, no config).
        // A paid provider (OE_VERIFY_PROVIDER + key) can layer on top later.
        $this->app->bind(EmailVerifier::class, MxVerifier::class);

        // Sending platforms: both built behind one manager, keys via settings.
        $this->app->singleton(OutboundManager::class, function ($app): OutboundManager {
            $settings = $app->make(Settings::class);

            $providers = [
                'instantly' => new InstantlyProvider($settings->resolve('instantly_api_key')),
                'lemlist' => new LemlistProvider($settings->resolve('lemlist_api_key')),
            ];

            return new OutboundManager($providers, $settings->resolve('outbound_provider') ?: 'instantly');
        });

        // Resolving the bare contract gives back the active (default) provider.
        $this->app->bind(OutboundProvider::class, function ($app): OutboundProvider {
            return $app->make(OutboundManager::class)->driver();
        });

        // Apollo lead source + enrichment; key via settings, cost metered.
        $this->app->bind(\App\Services\Apollo\ApolloClient::class, function ($app): \App\Services\Apollo\ApolloClient {
            $settings = $app->make(Settings::class);

            return new \App\Services\Apollo\ApolloClient(
                $settings->resolve('apollo_api_key'),
                $app->make(CostMeter::class),
            );
        });

        // HubSpot CRM sync; private-app token via settings.
        $this->app->bind(\App\Services\Crm\HubspotClient::class, function ($app): \App\Services\Crm\HubspotClient {
            $settings = $app->make(Settings::class);

            return new \App\Services\Crm\HubspotClient($settings->resolve('hubspot_api_key'));
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
