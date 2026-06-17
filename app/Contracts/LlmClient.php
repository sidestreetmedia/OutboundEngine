<?php

namespace App\Contracts;

/**
 * A text-in, text-out language model.
 *
 * Phase 1 binds this to NullLlmClient so the container resolves cleanly while
 * nothing is calling a model yet. The real Anthropic-backed client lands in
 * Phase 2 (Product Brain), where it reads decks/sites and writes copy.
 */
interface LlmClient
{
    /**
     * Run a single completion and return the model's text.
     *
     * @param  array<string, mixed>  $options  Per-call overrides (model, max_tokens, system, temperature).
     */
    public function complete(string $prompt, array $options = []): string;
}
