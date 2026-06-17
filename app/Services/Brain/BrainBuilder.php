<?php

namespace App\Services\Brain;

use App\Contracts\LlmClient;
use App\Models\Product;
use App\Models\ProductSource;
use App\Services\Brain\Concerns\ParsesLlmJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Builds a product's structured profile from its ingested sources.
 *
 * Hard rule: the profile is built ONLY from what's in the sources. The prompt
 * forbids invention, and anything not grounded in the material is left out.
 * Fabricated claims are the fastest way to burn a prospect and a sender domain,
 * so the engine refuses to manufacture them here at the root.
 */
class BrainBuilder
{
    use ParsesLlmJson;

    /** Cap on source characters sent to the model, to bound context + cost. */
    private const MAX_CORPUS_CHARS = 200_000;

    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * @return array<string, mixed> the saved profile
     */
    public function build(Product $product): array
    {
        $sources = $product->sources()
            ->where('status', ProductSource::STATUS_EXTRACTED)
            ->whereNotNull('extracted_text')
            ->get();

        if ($sources->isEmpty()) {
            throw new RuntimeException(
                "No ingested material for '{$product->name}'. Ingest at least one source before building the brain."
            );
        }

        $raw = $this->llm->complete($this->userPrompt($product, $sources), [
            'system' => $this->systemPrompt(),
            'max_tokens' => 4096,
            'temperature' => 0.2,
            'description' => 'product brain build',
            'costable_type' => $product->getMorphClass(),
            'costable_id' => $product->id,
        ]);

        $profile = $this->decodeJson($raw);

        $product->update([
            'profile' => $profile,
            'brain_built_at' => Carbon::now(),
        ]);

        return $profile;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a B2B positioning analyst. You are given a company's own material
        (decks, one-pagers, website copy) and must produce a FACTUAL profile used
        to write cold outreach.

        Absolute rules:
        - Use ONLY information present in the provided sources. Do not invent,
          infer beyond what's stated, or pad with generic marketing language.
        - If something isn't in the sources, omit it. Empty arrays are correct
          when there's no evidence — never manufacture a claim, metric, or logo.
        - Proof points must be grounded in the sources; include the supporting
          evidence (a short quote or paraphrase). If there are none, return [].

        Output STRICT JSON only — no markdown, no code fences, no commentary.
        Schema:
        {
          "what_we_do": "one tight paragraph in plain language",
          "icp": [ { "segment": "who this is for", "why": "why they need it" } ],
          "differentiators": [ "specific, defensible difference", ... ],
          "problems_solved": [ "concrete problem the buyer has", ... ],
          "proof_points": [ { "claim": "...", "evidence": "from the sources" }, ... ]
        }
        PROMPT;
    }

    /**
     * @param  Collection<int, ProductSource>  $sources
     */
    private function userPrompt(Product $product, Collection $sources): string
    {
        $prompt = "Company name: {$product->name}\n";

        if ($product->one_liner) {
            $prompt .= "Self-description: {$product->one_liner}\n";
        }

        $prompt .= "\nBuild the profile strictly from the company material below.\n\n=== SOURCES ===\n";
        $prompt .= $this->buildCorpus($sources);

        return $prompt;
    }

    /**
     * @param  Collection<int, ProductSource>  $sources
     */
    private function buildCorpus(Collection $sources): string
    {
        $parts = [];
        $used = 0;

        foreach ($sources as $source) {
            $title = $source->label ?: $source->original_name ?: $source->url ?: "source #{$source->id}";
            $chunk = "--- SOURCE: {$title} ---\n{$source->extracted_text}\n\n";

            if ($used + strlen($chunk) > self::MAX_CORPUS_CHARS) {
                $remaining = self::MAX_CORPUS_CHARS - $used;
                if ($remaining > 500) {
                    $parts[] = substr($chunk, 0, $remaining) . "\n[...truncated...]";
                }
                break;
            }

            $parts[] = $chunk;
            $used += strlen($chunk);
        }

        return implode("\n", $parts);
    }
}
