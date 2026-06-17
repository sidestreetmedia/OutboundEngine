<?php

namespace App\Services\Personalization;

use App\Contracts\LlmClient;
use App\Models\Product;
use App\Models\SequenceStep;
use App\Services\Brain\Concerns\ParsesLlmJson;

/**
 * Generates a handful of distinct subject-line variants for a step to A/B test.
 * Subject lines are the single biggest lever on whether a cold email gets opened
 * and replied to, so they're the first thing worth experimenting on. Each
 * variant is a different angle, and anything the spam linter flags is dropped.
 */
class VariantGenerator
{
    use ParsesLlmJson;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly SpamChecker $spam,
    ) {
    }

    /**
     * @return list<string>
     */
    public function subjectsForStep(SequenceStep $step, Product $product, int $count = 3): array
    {
        $raw = $this->llm->complete($this->prompt($step, $product, $count), [
            'system' => $this->systemPrompt(),
            'max_tokens' => 400,
            'temperature' => 0.9,
            'description' => 'subject variant generation',
            'costable_type' => $step->getMorphClass(),
            'costable_id' => $step->id,
        ]);

        $data = $this->decodeJson($raw);
        $subjects = is_array($data) ? ($data['subjects'] ?? $data) : [];

        $clean = [];
        foreach ((array) $subjects as $subject) {
            if (! is_string($subject)) {
                continue;
            }

            $subject = trim($subject);

            // Apply the same guardrail as generated copy — drop spammy/shouty/long.
            if ($subject !== '' && $this->spam->isClean($subject, '')) {
                $clean[$subject] = $subject;
            }
        }

        return array_slice(array_values($clean), 0, $count);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You write subject lines for cold outreach emails.

        Rules:
        - Short — ideally under 7 words. Lowercase is fine.
        - No hype, no clickbait, no ALL CAPS, no exclamation marks, no buzzwords,
          no fake "re:" unless contextually honest.
        - Each subject must take a genuinely DIFFERENT angle (e.g. a question, a
          concrete benefit, a curiosity hook, a specific observation).
        - Plain text. Never fabricate a fact to fill a subject.

        Output STRICT JSON only: {"subjects": ["...", "..."]}
        PROMPT;
    }

    private function prompt(SequenceStep $step, Product $product, int $count): string
    {
        $whatWeDo = $product->profile['what_we_do'] ?? $product->one_liner ?? $product->name;

        $prompt = "What we sell: {$whatWeDo}\n";
        $prompt .= "Sequence step angle: {$step->angle}\n";
        if (filled($step->subject_hint)) {
            $prompt .= "Subject guidance: {$step->subject_hint}\n";
        }
        $prompt .= "\nWrite {$count} distinct subject-line variants as JSON {\"subjects\": [...]}.";

        return $prompt;
    }
}
