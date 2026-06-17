<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Outbound\OutboundManager;
use Illuminate\Console\Command;

class CampaignConnectProviderCommand extends Command
{
    protected $signature = 'campaign:connect-provider
        {campaign : Campaign id or slug}
        {--provider= : instantly or lemlist}
        {--campaign-id= : The campaign id on that platform}';

    protected $description = 'Point a campaign at a provider-side campaign (where approved copy gets pushed)';

    public function handle(OutboundManager $manager): int
    {
        $campaign = Campaign::query()
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        $provider = $this->option('provider');
        $known = array_keys($manager->all());

        if (! $provider || ! in_array($provider, $known, true)) {
            $this->error('Pass --provider as one of: ' . implode(', ', $known));

            return self::FAILURE;
        }

        $providerCampaignId = $this->option('campaign-id');

        if (! $providerCampaignId) {
            $this->error('Pass --campaign-id (the campaign id on the platform).');

            return self::FAILURE;
        }

        $campaign->update([
            'provider' => $provider,
            'provider_campaign_id' => $providerCampaignId,
        ]);

        $this->info("'{$campaign->name}' connected to {$provider} campaign {$providerCampaignId}.");

        if (! $manager->driver($provider)->isConfigured()) {
            $this->warn("Heads up: no {$provider} API key is set yet — add it on the settings page before pushing.");
        }

        return self::SUCCESS;
    }
}
