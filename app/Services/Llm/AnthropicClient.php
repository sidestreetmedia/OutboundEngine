<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Services\Cost\CostMeter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API client. Every call records its token cost through the
 * CostMeter so the dashboard always reflects real burn. Returns the assistant's
 * text; structured-output parsing is the caller's job.
 */
class AnthropicClient implements LlmClient
{
    public function __construct(
        private readonly CostMeter $costMeter,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $version = '2023-06-01',
        private readonly string $baseUrl = 'https://api.anthropic.com',
    ) {
    }

    public function complete(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 4096),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (! empty($options['system'])) {
            $payload['system'] = $options['system'];
        }

        if (array_key_exists('temperature', $options)) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->version,
                'content-type' => 'application/json',
            ])
            ->timeout((int) ($options['timeout'] ?? 120))
            ->post('/v1/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic API error (HTTP {$response->status()}): " . $response->body()
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
        $usage = $data['usage'] ?? [];

        $attributes = array_filter([
            'description' => $options['description'] ?? 'LLM completion',
            'campaign_id' => $options['campaign_id'] ?? null,
            'costable_type' => $options['costable_type'] ?? null,
            'costable_id' => $options['costable_id'] ?? null,
        ], fn ($value) => ! is_null($value));

        $attributes['meta'] = ['model' => $model];

        $this->costMeter->recordLlmTokens(
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            $attributes,
        );
    }

    /**
     * Concatenate the text blocks from a Messages response.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractText(array $data): string
    {
        $text = '';

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }
}
