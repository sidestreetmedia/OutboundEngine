<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Reply;
use App\Services\Crm\CrmSync;
use Illuminate\Console\Command;

class HubspotPushCommand extends Command
{
    protected $signature = 'hubspot:push
        {campaign? : Campaign id or slug (optional; all campaigns if omitted)}
        {--limit=50 : Max leads to push}
        {--all : Re-push leads already in HubSpot}';

    protected $description = 'Push leads who replied positively into HubSpot as contacts (with CTA context)';

    public function handle(CrmSync $crm): int
    {
        if (! $crm->isReady()) {
            $this->error('No HubSpot token set. Add it on the settings page or in .env first.');

            return self::FAILURE;
        }

        $campaign = null;
        if ($this->argument('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->argument('campaign'))
                ->orWhere('slug', $this->argument('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->argument('campaign')}");

                return self::FAILURE;
            }
        }

        $leads = \App\Models\Lead::query()
            ->whereHas('replies', fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED))
            ->when($campaign, fn ($q) => $q->where('campaign_id', $campaign->id))
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('hubspot_contact_id'))
            ->limit((int) $this->option('limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No positive-reply leads to push.');

            return self::SUCCESS;
        }

        $this->info("Pushing {$leads->count()} positive-reply lead(s) to HubSpot...");

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $result = $crm->pushLead($lead);

            if ($result['ok']) {
                $synced++;
                $note = $result['noted'] ? '' : ' (note skipped)';
                $this->line("  ✓ {$lead->email} → contact {$result['contact_id']}{$note}");
            } elseif ($result['skipped']) {
                $skipped++;
                $this->line("  – {$lead->email}: {$result['skipped']}");
            } else {
                $failed++;
                $this->error("  ✗ {$lead->email}: {$result['error']}");
            }
        }

        $this->info('Done.');
        $this->line("  synced:  {$synced}");
        $this->line("  skipped: {$skipped}");
        $this->line("  failed:  {$failed}");

        return self::SUCCESS;
    }
}
