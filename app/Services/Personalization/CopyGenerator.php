<?php

namespace App\Services\Personalization;

use App\Contracts\LlmClient;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Product;
use App\Models\SequenceStep;
use App\Models\ValueProp;
use App\Services\Brain\Concerns\ParsesLlmJson;

/**
 * Generates the personalized copy for one (lead, step) as a draft Message.
 *
 * Everything the model is allowed to use is handed to it explicitly — the
 * prospect's real fields, one value prop, and (only if real) a proof point and
 * a trigger. The prompt forbids inventing anything else: no fake metrics, no
 * imaginary "I saw your post" personalization. Output lands as a draft for human
 * review; nothing here sends.
 */
class CopyGenerator
{
    use ParsesLlmJson;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ValuePropSelector $selector,
        private readonly SpamChecker $spam,
    ) {
    }

    public function generate(Lead $lead, Campaign $campaign, SequenceStep $step, Product $product): Message
    {
        $valueProp = $this->selector->forStep($lead, $product, max(0, $step->position - 1));
        $proof = $this->proof($valueProp, $product);
        $trigger = $this->trigger($lead);

        $raw = $this->llm->complete(
            $this->userPrompt($lead, $product, $step, $valueProp, $proof, $trigger),
            [
                'system' => $this->systemPrompt(),
                'max_tokens' => 1024,
                'temperature' => 0.7,
                'description' => 'message copy generation',
                'costable_type' => $lead->getMorphClass(),
                'costable_id' => $lead->id,
            ],
        );

        $data = $this->decodeJson($raw);
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        return Message::create([
            'lead_id' => $lead->id,
            'sequence_step_id' => $step->id,
            'campaign_id' => $campaign->id,
            'value_prop_id' => $valueProp?->id,
            'position' => $step->position,
            'subject' => $subject,
            'body' => $body,
            'status' => Message::STATUS_DRAFT,
            'generation' => [
                'value_prop' => $valueProp?->headline,
                'proof_used' => $proof,
                'trigger_used' => $trigger,
                'angle' => $step->angle,
                'spam_warnings' => $this->spam->check($subject, $body),
            ],
        ]);
    }

    private function proof(?ValueProp $valueProp, Product $product): ?string
    {
        if ($valueProp && filled($valueProp->proof_point)) {
            return $valueProp->proof_point;
        }

        $proofs = $product->profile['proof_points'] ?? [];

        if (is_array($proofs) && isset($proofs[0]) && is_array($proofs[0])) {
            return $proofs[0]['evidence'] ?? $proofs[0]['claim'] ?? null;
        }

        return null;
    }

    private function trigger(Lead $lead): ?string
    {
        $triggers = $lead->triggers ?? [];

        if (! is_array($triggers) || $triggers === []) {
            return null;
        }

        $first = $triggers[0];

        return is_array($first)
            ? ($first['summary'] ?? $first['text'] ?? null)
            : (string) $first;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You write cold outreach emails that sound like a real person typed them —
        short, specific, and low-pressure.

        Hard rules:
        - Use ONLY the facts provided below (the prospect's name, company, title,
          industry; the value prop; the proof). Never invent details about the
          prospect, and never fabricate a metric, result, or customer. Do not
          imply prior contact or "I saw your recent ..." unless a real trigger is
          given.
        - If a fact is unknown, omit it gracefully — never write a literal
          placeholder like "(unknown)" or "Hi there,," and don't guess a name.
        - Lead with the single value prop provided. Do not pitch multiple offers.
        - Use the proof only if one is provided, stated plainly without hype.
        - One clear, soft call to action. Match the step's angle and word limit.
        - Plain text only. No markdown, no links, no ALL CAPS, no exclamation-mark
          spam, no buzzwords.
        - Do NOT include a signature or sign-off — the sending platform adds it.

        Output STRICT JSON only: {"subject": "...", "body": "..."}
        PROMPT;
    }

    private function userPrompt(
        Lead $lead,
        Product $product,
        SequenceStep $step,
        ?ValueProp $valueProp,
        ?string $proof,
        ?string $trigger,
    ): string {
        $whatWeDo = $product->profile['what_we_do'] ?? $product->one_liner ?? $product->name;

        $prompt = "Prospect:\n";
        $prompt .= '- First name: ' . ($lead->first_name ?: '(unknown)') . "\n";
        $prompt .= '- Company: ' . ($lead->company ?: '(unknown)') . "\n";
        $prompt .= '- Title: ' . ($lead->title ?: '(unknown)') . "\n";
        $prompt .= '- Industry: ' . ($lead->industry ?: '(unknown)') . "\n\n";

        $prompt .= "What we sell: {$whatWeDo}\n\n";

        if ($valueProp) {
            $prompt .= "Lead with this value prop:\n";
            $prompt .= "- Headline: {$valueProp->headline}\n";
            if (filled($valueProp->problem)) {
                $prompt .= "- Problem it solves: {$valueProp->problem}\n";
            }
        }

        if ($proof) {
            $prompt .= "Real proof you may cite (only this): {$proof}\n";
        }

        if ($trigger) {
            $prompt .= "Real, current trigger you may reference: {$trigger}\n";
        }

        $prompt .= "\nThis is step {$step->position} of the sequence. Angle: {$step->angle}.\n";
        if (filled($step->subject_hint)) {
            $prompt .= "Subject guidance: {$step->subject_hint}\n";
        }
        if (filled($step->instructions)) {
            $prompt .= "Instructions: {$step->instructions}\n";
        }

        $prompt .= "\nWrite the email now as JSON {\"subject\": \"...\", \"body\": \"...\"}.";

        return $prompt;
    }
}
