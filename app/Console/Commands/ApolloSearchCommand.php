<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Apollo\ApolloClient;
use Illuminate\Console\Command;

class ApolloSearchCommand extends Command
{
    protected $signature = 'apollo:search
        {campaign : Campaign id or slug to import into}
        {--titles= : Comma-separated job titles, e.g. "Owner,Founder,CEO"}
        {--keywords= : Comma-separated company keywords, e.g. "med spa,dental"}
        {--locations= : Comma-separated locations, e.g. "United States,Texas"}
        {--employees= : Employee range as min,max, e.g. "1,50"}
        {--limit=25 : How many to pull (max 100 per page)}
        {--page=1 : Result page}
        {--dry-run : Show the query without calling Apollo}';

    protected $description = 'Source net-new prospects from Apollo into a campaign (free; emails are revealed separately)';

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

        $filters = array_filter([
            'person_titles' => $this->csv('titles'),
            'q_organization_keyword_tags' => $this->csv('keywords'),
            'person_locations' => $this->csv('locations'),
            'organization_num_employees_ranges' => $this->employees(),
        ]);

        if (empty($filters['person_titles']) && empty($filters['q_organization_keyword_tags']) && empty($filters['person_locations'])) {
            $this->error('Give some criteria: at least one of --titles, --keywords, or --locations.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — would search Apollo with:');
            $this->line('  ' . json_encode($filters));
            $this->line('Search is free and imports no emails. Re-run without --dry-run to import.');

            return self::SUCCESS;
        }

        $result = $apollo->searchPeople($filters, (int) $this->option('page'), (int) $this->option('limit'));

        if (! $result['ok']) {
            $this->error("Apollo search failed: {$result['error']}");

            return self::FAILURE;
        }

        if ($result['people'] === []) {
            $this->info('No matches for those filters.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($result['people'] as $person) {
            $apolloId = $person['id'] ?? null;

            if (! $apolloId) {
                continue;
            }

            if (Lead::where('apollo_id', $apolloId)->exists()) {
                $skipped++;

                continue;
            }

            $org = $person['organization'] ?? [];
            $domain = $org['primary_domain'] ?? $this->hostOf($org['website_url'] ?? null);
            $placeholder = "apollo-{$apolloId}@unenriched.invalid";

            Lead::create([
                'campaign_id' => $campaign->id,
                'source' => 'apollo',
                'status' => Lead::STATUS_NEW,
                'apollo_id' => $apolloId,
                'email' => $placeholder,
                'email_normalized' => $placeholder,
                'first_name' => $person['first_name'] ?? null,
                'last_name' => $person['last_name'] ?? null,
                'title' => $person['title'] ?? null,
                'company' => $org['name'] ?? ($person['organization_name'] ?? null),
                'company_domain' => $domain,
                'industry' => $org['industry'] ?? null,
                'linkedin_url' => $person['linkedin_url'] ?? null,
            ]);
            $imported++;
        }

        $this->info('Done.');
        $this->line("  imported: {$imported} prospect(s)");
        $this->line("  skipped:  {$skipped} (already sourced)");
        $this->line("  {$result['total']} total match this search in Apollo");
        $this->newLine();
        $this->line('Emails are locked. Reveal them (costs credits) with:  php artisan apollo:enrich ' . $campaign->slug);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function csv(string $option): array
    {
        $raw = (string) $this->option($option);

        return collect(explode(',', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function employees(): array
    {
        $range = trim((string) $this->option('employees'));

        return $range === '' ? [] : [$range];
    }

    private function hostOf(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
