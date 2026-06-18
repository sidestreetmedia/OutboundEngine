<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Models\CostEvent;
use App\Services\Cost\CostMeter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemma via the Gemini API (generativelanguage.googleapis.com). Gemma is
 * free on the Gemini API's free tier, so calls are metered at $0 — the token
 * counts are still recorded so the dashboard shows usage. Auth is a Google AI
 * Studio API key sent as x-goog-api-key.
 *
 * Note: Gemma has no separate system role, so any system prompt is folded into
 * the user turn (matching how Gemma's own chat template handles it) rather than
 * sent as systemInstruction — that keeps it compatible across Gemma variants.
 */
class GoogleLlmClient implements LlmClient
{
    public const DEFAULT_MODEL = 'gemma-3-27b-it';

    private const BASE = 'https://generativelanguage.googleapis.com';

    public function __construct(
        private readonly CostMeter $costMeter,
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
    ) {
    }

    public function complete(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;

        $system = $options['system'] ?? null;
        $text = filled($system) ? trim($system) . "\n\n" . $prompt : $prompt;

        $generationConfig = ['maxOutputTokens' => (int) ($options['max_tokens'] ?? 4096)];

        if (array_key_exists('temperature', $options)) {
            $generationConfig['temperature'] = $options['temperature'];
        }

        $response = Http::baseUrl(self::BASE)
            ->withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ])
            ->timeout((int) ($options['timeout'] ?? 120))
            ->post("/v1beta/models/{$model}:generateContent", [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $text]],
                ]],
                'generationConfig' => $generationConfig,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Google Gemini API error (HTTP {$response->status()}): " . $response->body()
            );
        }

        $data = $response->json();

        $this->recordCost($data, $model, $options);

        return $this->extractText($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function recordCost(array $data, string $model, array $options): void
    {
        $usage = $data['usageMetadata'] ?? [];
        $inputTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $outputTokens = (int) ($usage['candidatesTokenCount'] ?? 0);

        $attributes = array_filter([
            'description' => $options['description'] ?? 'LLM completion',
            'campaign_id' => $options['campaign_id'] ?? null,
            'costable_type' => $options['costable_type'] ?? null,
            'costable_id' => $options['costable_id'] ?? null,
        ], fn ($value) => ! is_null($value));

        // Free on the Gemini API free tier — $0, but keep the token counts.
        $this->costMeter->record(CostEvent::CATEGORY_LLM, 0.0, array_merge([
            'provider' => 'google',
            'quantity' => $inputTokens + $outputTokens,
            'unit' => 'tokens',
            'meta' => [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'free_tier' => true,
            ],
        ], $attributes));
    }

    /**
     * Concatenate the text parts from the first candidate.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractText(array $data): string
    {
        $text = '';

        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }

        return $text;
    }
}
