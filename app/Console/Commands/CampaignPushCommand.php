<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Message;
use App\Services\Compliance\SuppressionList;
use App\Services\Outbound\OutboundManager;
use Illuminate\Console\Command;

class CampaignPushCommand extends Command
{
    protected $signature = 'campaign:push
        {campaign : Campaign id or slug}
        {--limit=100 : Max leads to push this run}';

    protected $description = 'Push approved copy for verified leads to the connected sending platform';

    public function handle(OutboundManager $manager, SuppressionList $suppression): int
    {
        $campaign = Campaign::query()
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        if (! $campaign->provider || ! $campaign->provider_campaign_id) {
            $this->error('No provider connected. Run campaign:connect-provider first.');

            return self::FAILURE;
        }

        $provider = $manager->driver($campaign->provider);

        if (! $provider->isConfigured()) {
            $this->error("No {$campaign->provider} API key set. Add it on the settings page or in .env.");

            return self::FAILURE;
        }

        $leads = $campaign->leads()
            ->where('status', Lead::STATUS_VERIFIED)
            ->whereNull('pushed_at')
            ->whereHas('messages', fn ($q) => $q->where('status', Message::STATUS_APPROVED))
            ->limit((int) $this->option('limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('Nothing to push — no verified leads with approved, un-pushed copy.');

            return self::SUCCESS;
        }

        $this->info("Pushing {$leads->count()} lead(s) to {$campaign->provider} campaign {$campaign->provider_campaign_id}...");

        $pushed = 0;
        $queued = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            // Compliance gate: never hand a suppressed address to a sender, even
            // if it slipped back in via a re-import.
            if ($suppression->isSuppressed($lead->email)) {
                $lead->update(['status' => Lead::STATUS_SUPPRESSED]);
                $skipped++;

                continue;
            }

            $messages = $lead->messages()
                ->where('status', Message::STATUS_APPROVED)
                ->orderBy('position')
                ->get();

            if ($messages->isEmpty()) {
                continue;
            }

            $variables = [];
            foreach ($messages as $message) {
                $variables["oe_subject_{$message->position}"] = (string) $message->subject;
                $variables["oe_body_{$message->position}"] = (string) $message->body;
            }

            // Per-prospect proof page, if one's been set up — link it from the sequence.
            if (filled($lead->public_token)) {
                $variables['oe_landing_url'] = url('/p/' . $lead->public_token);
            }

            $result = $provider->pushLead($campaign->provider_campaign_id, [
                'email' => $lead->email,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'company' => $lead->company,
                'company_domain' => $lead->company_domain,
                'title' => $lead->title,
                'linkedin_url' => $lead->linkedin_url,
                'variables' => $variables,
            ]);

            if ($result->ok) {
                $lead->update([
                    'provider_lead_id' => $result->providerLeadId,
                    'pushed_at' => now(),
                ]);
                $messages->each(fn (Message $m) => $m->update(['status' => Message::STATUS_QUEUED]));

                $pushed++;
                $queued += $messages->count();
            } else {
                $failed++;
                $this->error("  {$lead->email}: {$result->error}");
            }
        }

        $this->info('Done.');
        $this->line("  pushed:     {$pushed} lead(s)");
        $this->line("  queued:     {$queued} message(s)");
        $this->line("  suppressed: {$skipped} (on the do-not-contact list)");
        $this->line("  failed:     {$failed}");
        $this->newLine();
        $this->line("Your {$campaign->provider} sequence should reference the pushed copy as");
        $this->line('  {{oe_subject_1}} / {{oe_body_1}}, {{oe_subject_2}} / {{oe_body_2}}, ...');

        return self::SUCCESS;
    }
}
