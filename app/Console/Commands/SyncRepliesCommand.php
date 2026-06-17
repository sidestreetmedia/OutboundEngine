<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Reply;
use App\Services\Outbound\Dto\InboundReply;
use App\Services\Outbound\OutboundManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncRepliesCommand extends Command
{
    protected $signature = 'replies:sync
        {campaign : Campaign id or slug}
        {--since= : Only pull replies after this date (e.g. 2026-06-01)}';

    protected $description = 'Pull replies and bounces back from the sending platform';

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

        if (! $campaign->provider || ! $campaign->provider_campaign_id) {
            $this->error('No provider connected. Run campaign:connect-provider first.');

            return self::FAILURE;
        }

        $provider = $manager->driver($campaign->provider);

        if (! $provider->isConfigured()) {
            $this->error("No {$campaign->provider} API key set.");

            return self::FAILURE;
        }

        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;

        $replies = $provider->fetchReplies($campaign->provider_campaign_id, $since);

        if ($replies === []) {
            $this->info('No replies returned.');

            return self::SUCCESS;
        }

        $new = 0;
        $duplicates = 0;
        $bounces = 0;

        foreach ($replies as $reply) {
            if ($this->alreadyStored($campaign->provider, $reply)) {
                $duplicates++;

                continue;
            }

            $lead = Lead::where('email_normalized', mb_strtolower(trim($reply->email)))->first();

            Reply::create([
                'lead_id' => $lead?->id,
                'campaign_id' => $campaign->id,
                'provider' => $campaign->provider,
                'provider_message_id' => $reply->providerMessageId,
                'from_email' => $reply->email,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'is_bounce' => $reply->isBounce,
                'is_auto_reply' => $reply->isAutoReply,
                'received_at' => $reply->receivedAt,
                'classification' => $reply->isAutoReply ? Reply::CLASS_AUTO_REPLY : null,
                'meta' => $reply->raw,
            ]);
            $new++;

            // A bounce means the address is dead — stop contacting it.
            if ($reply->isBounce && $lead) {
                $lead->update(['status' => Lead::STATUS_SUPPRESSED]);
                $bounces++;
            }
        }

        $this->info('Done.');
        $this->line("  new replies: {$new}");
        $this->line("  duplicates:  {$duplicates} (already pulled)");
        $this->line("  bounces:     {$bounces} (leads suppressed)");
        $this->line('  next: classify with  php artisan replies:classify ' . $campaign->slug);

        return self::SUCCESS;
    }

    private function alreadyStored(string $provider, InboundReply $reply): bool
    {
        if (! $reply->providerMessageId) {
            return false;
        }

        return Reply::where('provider', $provider)
            ->where('provider_message_id', $reply->providerMessageId)
            ->exists();
    }
}
