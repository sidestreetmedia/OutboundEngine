<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Proof\AuditReporter;
use App\Services\Proof\PresenceAuditor;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class BuildAuditsCommand extends Command
{
    private const FREE_MAIL = [
        'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com',
        'icloud.com', 'proton.me', 'protonmail.com', 'live.com', 'msn.com',
    ];

    protected $signature = 'audit:build
        {campaign : Campaign id or slug}
        {--limit=25 : Max leads to audit this run}
        {--force : Re-audit leads that already have an audit}';

    protected $description = 'Build public-presence audits (real signals + a grounded summary) for a campaign';

    public function handle(PresenceAuditor $auditor, AuditReporter $reporter, Settings $settings): int
    {
        $campaign = Campaign::query()
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        $canSummarize = filled($settings->resolve('anthropic_api_key'));
        if (! $canSummarize) {
            $this->warn('No Anthropic key set — building audits with findings only, no written summary.');
        }

        $force = (bool) $this->option('force');

        $leads = $campaign->leads()
            ->when(! $force, fn ($q) => $q->whereDoesntHave('audit', fn ($q) => $q->where('status', Audit::STATUS_DONE)))
            ->limit((int) $this->option('limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('Nothing to audit.');

            return self::SUCCESS;
        }

        $this->info("Auditing {$leads->count()} lead(s) for '{$campaign->name}'...");

        $audited = 0;
        $reused = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $this->assignToken($lead);
            $url = $this->resolveUrl($lead);

            if (! $url) {
                Audit::updateOrCreate(['lead_id' => $lead->id], [
                    'status' => Audit::STATUS_SKIPPED,
                    'error' => 'No company domain to audit.',
                ]);
                $skipped++;

                continue;
            }

            $domain = parse_url($url, PHP_URL_HOST) ?: $url;

            // Reuse a sibling lead's audit of the same domain instead of re-fetching.
            $existing = $force ? null : Audit::where('domain', $domain)
                ->where('status', Audit::STATUS_DONE)
                ->whereNotNull('findings')
                ->latest('fetched_at')
                ->first();

            if ($existing) {
                $audit = Audit::updateOrCreate(['lead_id' => $lead->id], [
                    'domain' => $domain,
                    'url' => $existing->url,
                    'status' => Audit::STATUS_DONE,
                    'findings' => $existing->findings,
                    'summary' => $existing->summary,
                    'fetched_at' => $existing->fetched_at,
                ]);
                $reused++;
            } else {
                $result = $auditor->audit($url);

                $audit = Audit::updateOrCreate(['lead_id' => $lead->id], [
                    'domain' => $domain,
                    'url' => $result['url'],
                    'status' => $result['ok'] ? Audit::STATUS_DONE : Audit::STATUS_FAILED,
                    'findings' => $result['ok'] ? $result['findings'] : null,
                    'error' => $result['error'],
                    'fetched_at' => $result['ok'] ? now() : null,
                ]);

                $result['ok'] ? $audited++ : $failed++;
            }

            if ($canSummarize && $audit->isDone() && blank($audit->summary)) {
                try {
                    $audit->update(['summary' => $reporter->summarize($audit)]);
                } catch (Throwable $e) {
                    $this->warn("  summary failed for {$lead->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info('Done.');
        $this->line("  audited: {$audited}");
        $this->line("  reused:  {$reused} (same domain as another lead)");
        $this->line("  skipped: {$skipped} (no domain)");
        $this->line("  failed:  {$failed}");

        return self::SUCCESS;
    }

    private function resolveUrl(Lead $lead): ?string
    {
        $domain = $lead->company_domain;

        if (! $domain && $lead->email) {
            $at = strrpos($lead->email, '@');
            $candidate = $at !== false ? mb_strtolower(substr($lead->email, $at + 1)) : null;

            if ($candidate && ! in_array($candidate, self::FREE_MAIL, true)) {
                $domain = $candidate;
            }
        }

        return $domain ? 'https://' . $domain : null;
    }

    private function assignToken(Lead $lead): void
    {
        if (blank($lead->public_token)) {
            $lead->update(['public_token' => Str::lower(Str::random(12))]);
        }
    }
}
