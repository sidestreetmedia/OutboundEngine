<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use RuntimeException;

/**
 * Placeholder LLM client so the container resolves cleanly in Phase 1.
 * Throws if anything actually tries to call a model. The real Anthropic
 * client replaces this binding in Phase 2 (Product Brain).
 */
class NullLlmClient implements LlmClient
{
    public function complete(string $prompt, array $options = []): string
    {
        throw new RuntimeException(
            'LLM client not configured yet — the Anthropic client lands in Phase 2 (Product Brain).'
        );
    }
}
