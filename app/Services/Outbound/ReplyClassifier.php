<?php

namespace App\Services\Outbound;

use App\Contracts\LlmClient;
use App\Models\Reply;
use App\Services\Brain\Concerns\ParsesLlmJson;

/**
 * Sorts a reply into one fixed label so the funnel can be measured and the right
 * follow-up can happen. Interest is the metric that matters; everything else is
 * triage (objections to handle, timing to revisit, unsubscribes to honor).
 */
class ReplyClassifier
{
    use ParsesLlmJson;

    private const LABELS = [
        Reply::CLASS_INTERESTED,
        Reply::CLASS_OBJECTION,
        Reply::CLASS_NOT_NOW,
        Reply::CLASS_OOO,
        Reply::CLASS_UNSUBSCRIBE,
        Reply::CLASS_AUTO_REPLY,
        Reply::CLASS_OTHER,
    ];

    public function __construct(private readonly LlmClient $llm)
    {
    }

    public function classify(Reply $reply): string
    {
        $raw = $this->llm->complete($this->prompt($reply), [
            'system' => $this->systemPrompt(),
            'max_tokens' => 200,
            'temperature' => 0,
            'description' => 'reply classification',
            'costable_type' => $reply->getMorphClass(),
            'costable_id' => $reply->id,
        ]);

        $data = $this->decodeJson($raw);
        $label = is_array($data) ? ($data['classification'] ?? null) : null;

        return in_array($label, self::LABELS, true) ? $label : Reply::CLASS_OTHER;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You classify replies to cold outreach emails into exactly one label:

        - interested: wants to talk, asks for more info, requests a call/demo, or
          otherwise positive and engaged.
        - objection: engaged but pushing back (too expensive, already have a
          vendor, not convinced, wrong person but responding).
        - not_now: open in principle but the timing is off ("reach back in Q3").
        - ooo: an out-of-office auto-response.
        - unsubscribe: asks to stop, be removed, or not be contacted again.
        - auto_reply: an automated response that isn't an out-of-office.
        - other: anything that fits none of the above.

        Judge only from the text. Do not invent context. Choose the single best
        label. Output STRICT JSON only: {"classification": "<label>"}
        PROMPT;
    }

    private function prompt(Reply $reply): string
    {
        $subject = $reply->subject ?: '(no subject)';
        $body = $reply->body ?: '(no body)';

        return "Subject: {$subject}\n\nBody:\n{$body}\n\nClassify this reply.";
    }
}
