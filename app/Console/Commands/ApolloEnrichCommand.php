<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Apollo\ApolloClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ApolloEnrichCommand extends Command
{
    protected $signature = 'apollo:enrich
        {campaign : Campaign id or slug}
        {--limit=25 : Max leads to enrich this run (caps the spend)}
        {--yes : Skip the spend confirmation}';

    protected $description = 'Reveal emails + enrich Apollo-sourced leads (COSTS Apollo credits)';

    public function handle(ApolloClient $apollo): int
    {
        if (! $apollo->isConfigured()) {
            $this->error('No Apollo API key set. Add it on the settings page or in .env first.');

            return self::FAILURE;
        }

        $campaign = Campaign::query()
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        $leads = $campaign->leads()
            ->whereNotNull('apollo_id')
            ->where('email', 'like', '%@unenriched.invalid')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('Nothing to enrich — no Apollo-sourced leads with locked emails.');

            return self::SUCCESS;
        }

        // Spend gate — the engine never buys anything without an explicit yes.
        $count = $leads->count();
        $rate = (float) config('outbound.apollo.cost_per_credit', 0.0);
        $estimate = $rate > 0 ? sprintf(' (~$%.2f at your rate)', $count * $rate) : '';
        $this->warn("This reveals {$count} email(s) and spends up to {$count} Apollo credit(s){$estimate}.");

        if (! $this->option('yes') && ! $this->confirm('Proceed?')) {
            $this->line('Aborted — no credits spent.');

            return self::SUCCESS;
        }

        $enriched = 0;
        $noEmail = 0;
        $noMatch = 0;
        $merged = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $result = $apollo->enrichPerson($lead->apollo_id, [
                'campaign_id' => $campaign->id,
                'costable_type' => Lead::class,
                'costable_id' => $lead->id,
            ]);

            if (! $result['ok']) {
                $failed++;
                $this->error("  {$lead->apollo_id}: {$result['error']}");

                continue;
            }

            $person = $result['person'];

            if ($person === null) {
                $noMatch++;

                continue;
            }

            $email = $this->revealedEmail($person);

            if (! $email) {
                $noEmail++;
                $this->fillProfile($lead, $person);
                $lead->save();

                continue;
            }

            // Another lead already owns this address — the sourced one is a dup.
            $normalized = mb_strtolower(trim($email));
            $clash = Lead::where('email_normalized', $normalized)->where('id', '!=', $lead->id)->exists();

            if ($clash) {
                $lead->delete();
                $merged++;

                continue;
            }

            $lead->email = $email;
            $lead->email_normalized = $normalized;
            $this->fillProfile($lead, $person);
            $lead->triggers = $this->deriveTriggers($person) ?: $lead->triggers;
            $lead->save();
            $enriched++;
        }

        $this->info('Done.');
        $this->line("  enriched: {$enriched} (email revealed)");
        $this->line("  no email: {$noEmail} (matched, no address on this plan)");
        $this->line("  no match: {$noMatch}");
        $this->line("  merged:   {$merged} (duplicate of an existing lead)");
        $this->line("  failed:   {$failed}");
        $this->newLine();
        $this->line('Next: verify deliverability with  php artisan leads:verify');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function revealedEmail(array $person): ?string
    {
        $email = $person['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        // Apollo returns placeholders when an address isn't actually unlocked.
        if (str_contains($email, 'email_not_unlocked') || str_contains($email, 'domain.com')) {
            return null;
        }

        return $email;
    }

    /**
     * Fill only fields we don't already have, from the enrichment payload.
     *
     * @param  array<string, mixed>  $person
     */
    private function fillProfile(Lead $lead, array $person): void
    {
        $org = $person['organization'] ?? [];

        $lead->first_name = $lead->first_name ?: ($person['first_name'] ?? null);
        $lead->last_name = $lead->last_name ?: ($person['last_name'] ?? null);
        $lead->title = $lead->title ?: ($person['title'] ?? null);
        $lead->company = $lead->company ?: ($org['name'] ?? null);
        $lead->company_domain = $lead->company_domain ?: ($org['primary_domain'] ?? null);
        $lead->industry = $lead->industry ?: ($org['industry'] ?? null);
        $lead->linkedin_url = $lead->linkedin_url ?: ($person['linkedin_url'] ?? null);
    }

    /**
     * Derive a real trigger from Apollo data — a recent move into the role.
     * Only emitted when the data actually supports it; never invented.
     *
     * @param  array<string, mixed>  $person
     * @return list<array{type:string, summary:string}>
     */
    private function deriveTriggers(array $person): array
    {
        $history = $person['employment_history'] ?? [];

        if (! is_array($history)) {
            return [];
        }

        foreach ($history as $job) {
            if (! is_array($job) || empty($job['current']) || empty($job['start_date'])) {
                continue;
            }

            try {
                $start = Carbon::parse($job['start_date']);
            } catch (\Throwable) {
                continue;
            }

            if ($start->greaterThan(Carbon::now()->subMonths(9))) {
                $title = $job['title'] ?? ($person['title'] ?? 'their role');
                $org = $job['organization_name'] ?? ($person['organization']['name'] ?? 'the company');

                return [[
                    'type' => 'new_role',
                    'summary' => "Recently stepped into {$title} at {$org} ({$start->format('M Y')})",
                ]];
            }
        }

        return [];
    }
}
