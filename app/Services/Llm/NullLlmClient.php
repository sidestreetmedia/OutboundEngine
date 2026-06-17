<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use RuntimeException;

/**
 * Fallback LLM client used when no ANTHROPIC_API_KEY is configured. Throws if
 * anything tries to call a model, so a missing key fails loudly instead of
 * silently skipping the brain. Set the key to activate the real client.
 */
class NullLlmClient implements LlmClient
{
    public function complete(string $prompt, array $options = []): string
    {
        throw new RuntimeException(
            'No LLM configured. Set ANTHROPIC_API_KEY to enable the brain and copy generation.'
        );
    }
}
