<?php

namespace App\Services\Proof;

use App\Contracts\LlmClient;
use App\Models\Audit;

/**
 * Turns the raw audit findings into a couple of honest sentences a salesperson
 * could actually say. The model is handed only what was observed and is forbidden
 * from inventing anything — no traffic numbers, no rankings, no made-up metrics.
 * Thin findings get a thin summary; that's correct, not a bug.
 */
class AuditReporter
{
    public function __construct(private readonly LlmClient $llm)
    {
    }

    public function summarize(Audit $audit): string
    {
        $company = $audit->lead?->company ?: ($audit->domain ?: 'this company');

        $raw = $this->llm->complete($this->prompt($audit->findings ?? [], $company), [
            'system' => $this->systemPrompt(),
            'max_tokens' => 300,
            'temperature' => 0.5,
            'description' => 'audit summary',
            'costable_type' => $audit->getMorphClass(),
            'costable_id' => $audit->id,
        ]);

        return trim($raw);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You write a short, honest observation about a company's website that a
        salesperson could reference — based ONLY on the audit findings provided.

        Rules:
        - 2-3 sentences, plain and specific.
        - Mention one genuine strength and one genuine gap, but only if the
          findings actually support them.
        - NEVER invent a metric, number, ranking, traffic figure, or any claim
          that isn't in the findings. If the findings are thin, say less.
        - No hype, no salesy adjectives. Write like a knowledgeable peer who
          actually looked at the site.
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $findings
     */
    private function prompt(array $findings, string $company): string
    {
        $lines = $this->readableFindings($findings);

        return "Company: {$company}\n\nWhat we observed on their site:\n{$lines}\n\nWrite the short observation.";
    }

    /**
     * @param  array<string, mixed>  $findings
     */
    private function readableFindings(array $findings): string
    {
        if ($findings === []) {
            return '- (the site could not be read)';
        }

        $yn = fn ($v) => $v ? 'yes' : 'no';
        $social = $findings['social_links'] ?? [];

        $lines = [
            'HTTPS: ' . $yn($findings['https'] ?? false),
            'Page title: ' . ($findings['title'] ?? '(none)'),
            'Meta description: ' . (($findings['has_meta_description'] ?? false) ? 'present' : 'missing'),
            'Mobile viewport: ' . $yn($findings['mobile_viewport'] ?? false),
            'Open Graph (social preview) tags: ' . $yn($findings['open_graph'] ?? false),
            'Analytics installed: ' . $yn($findings['analytics'] ?? false),
            'Marketing/tracking pixel: ' . $yn($findings['tracking_pixel'] ?? false),
            'Structured data: ' . $yn($findings['structured_data'] ?? false),
            'Platform: ' . ($findings['platform'] ?? 'unknown'),
            'Social links found: ' . (empty($social) ? 'none' : implode(', ', $social)),
        ];

        return '- ' . implode("\n- ", $lines);
    }
}
