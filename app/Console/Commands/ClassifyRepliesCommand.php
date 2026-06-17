<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Reply;
use App\Services\Outbound\ReplyClassifier;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;

class ClassifyRepliesCommand extends Command
{
    protected $signature = 'replies:classify
        {campaign : Campaign id or slug}
        {--limit=100 : Max replies to classify this run}
        {--all : Re-classify, including already-classified replies}';

    protected $description = 'Classify replies (interested / objection / not now / OOO / unsubscribe / ...)';

    public function handle(ReplyClassifier $classifier, Settings $settings): int
    {
        if (blank($settings->resolve('anthropic_api_key'))) {
            $this->error('No Anthropic key set. Add it on the settings page or in .env before classifying.');

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

        $query = $campaign->replies()->with('lead');

        if (! $this->option('all')) {
            $query->whereNull('classification');
        }

        $replies = $query->limit((int) $this->option('limit'))->get();

        if ($replies->isEmpty()) {
            $this->info('Nothing to classify.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$replies->count()} repl(ies)...");

        $tally = [];
        $suppressed = 0;

        foreach ($replies as $reply) {
            $label = $classifier->classify($reply);
            $reply->update(['classification' => $label]);
            $tally[$label] = ($tally[$label] ?? 0) + 1;

            // Honor unsubscribes immediately.
            if ($label === Reply::CLASS_UNSUBSCRIBE && $reply->lead) {
                $reply->lead->update(['status' => Lead::STATUS_SUPPRESSED]);
                $suppressed++;
            }
        }

        $this->info('Done.');
        foreach ($tally as $label => $count) {
            $this->line(sprintf('  %-12s %d', $label, $count));
        }

        $positive = $tally[Reply::CLASS_INTERESTED] ?? 0;
        $this->newLine();
        $this->line("  <info>interested (positive replies): {$positive}</info>");
        if ($suppressed > 0) {
            $this->line("  unsubscribed → suppressed: {$suppressed}");
        }

        return self::SUCCESS;
    }
}
