<?php

namespace App\Services\Brain;

use App\Contracts\LlmClient;
use App\Models\Product;
use App\Services\Brain\Concerns\ParsesLlmJson;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Second brain pass: turns a product's structured profile into the personas it
 * targets and a value-prop library mapped to them. This is what Phase 4 pulls
 * from so every email leads with one real value prop for one real persona.
 *
 * Like the profile pass, it's grounded strictly in the profile — proof points
 * come only from the profile's evidence, never invented. Rebuilds are idempotent:
 * the product's existing personas and value props are replaced, not appended.
 */
class LibraryBuilder
{
    use ParsesLlmJson;

    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * @return array<string, mixed> the raw library data
     */
    public function build(Product $product): array
    {
        if (! $product->hasBrain()) {
            throw new RuntimeException(
                "'{$product->name}' has no profile yet. Run product:build-brain before building the library."
            );
        }

        $raw = $this->llm->complete($this->userPrompt($product), [
            'system' => $this->systemPrompt(),
            'max_tokens' => 4096,
            'temperature' => 0.3,
            'description' => 'product library build',
            'costable_type' => $product->getMorphClass(),
            'costable_id' => $product->id,
        ]);

        $data = $this->decodeJson($raw);

        $this->persist($product, $data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Product $product, array $data): void
    {
        DB::transaction(function () use ($product, $data) {
            // Rebuild from scratch — value props first (they reference personas).
            $product->valueProps()->delete();
            $product->personas()->delete();

            $personaIds = [];

            foreach ($data['personas'] ?? [] as $row) {
                if (blank($row['name'] ?? null)) {
                    continue;
                }

                $persona = $product->personas()->create([
                    'name' => $row['name'],
                    'role' => $row['role'] ?? null,
                    'seniority' => $row['seniority'] ?? null,
                    'okrs' => $row['okrs'] ?? null,
                    'pains' => $row['pains'] ?? null,
                ]);

                $personaIds[$this->key($persona->name)] = $persona->id;
            }

            foreach ($data['value_props'] ?? [] as $row) {
                if (blank($row['headline'] ?? null)) {
                    continue;
                }

                $product->valueProps()->create([
                    'persona_id' => $personaIds[$this->key($row['persona'] ?? '')] ?? null,
                    'headline' => $row['headline'],
                    'body' => $row['body'] ?? null,
                    'problem' => $row['problem'] ?? null,
                    'proof_point' => $row['proof'] ?? ($row['proof_point'] ?? null),
                ]);
            }
        });
    }

    private function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a B2B messaging strategist. Given a company's structured profile,
        derive the buyer personas it should target and a library of value props
        mapped to them, for use in cold outreach.

        Absolute rules:
        - Ground everything in the profile provided. Do not invent personas,
          metrics, or proof. Proof points may only come from the profile's
          proof_points/evidence; if a value prop has no real proof, leave it empty.
        - Map each value prop to a persona by exact persona name. Company-level
          value props that fit everyone use an empty "persona" string.

        Output STRICT JSON only — no markdown, no code fences, no commentary.
        Schema:
        {
          "personas": [
            { "name": "short label", "role": "job title", "seniority": "ic|manager|executive",
              "okrs": ["what they're measured on"], "pains": ["problems they feel"] }
          ],
          "value_props": [
            { "persona": "persona name or empty", "headline": "the promise",
              "problem": "buyer problem it addresses", "proof": "evidence from the profile or empty" }
          ]
        }
        PROMPT;
    }

    private function userPrompt(Product $product): string
    {
        return "Company: {$product->name}\n\n"
            . "Structured profile (JSON):\n"
            . json_encode($product->profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\nDerive the personas and value-prop library strictly from this profile.";
    }
}
