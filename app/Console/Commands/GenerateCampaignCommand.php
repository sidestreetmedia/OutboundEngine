<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Sequence;
use App\Services\Personalization\CopyGenerator;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;
use Throwable;

class GenerateCampaignCommand extends Command
{
    protected $signature = 'campaign:generate
        {campaign : Campaign id or slug}
        {--limit=25 : Max leads to generate copy for this run}
        {--steps= : Only generate the first N steps of the sequence}';

    protected $description = 'Generate draft copy for a campaign\'s verified leads (one value prop per step)';

    public function handle(CopyGenerator $generator, Settings $settings): int
    {
        if (blank($settings->resolve('anthropic_api_key'))) {
            $this->error('No Anthropic key set. Add it on the settings page or in .env before generating copy.');

            return self::FAILURE;
        }

        $campaign = Campaign::query()
            ->with('product')
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        if (! $campaign->product) {
            $this->error("Campaign '{$campaign->name}' isn't linked to a product. Set one with campaign:create --product=...");

            return self::FAILURE;
        }

        if (! $campaign->product->hasBrain() || $campaign->product->valueProps()->count() === 0) {
            $this->error("Product '{$campaign->product->name}' has no value-prop library yet. Run product:build-brain then product:build-library.");

            return self::FAILURE;
        }

        $sequence = $campaign->sequences()->where('status', Sequence::STATUS_ACTIVE)->latest('id')->first();

        if (! $sequence || $sequence->steps()->count() === 0) {
            $this->error("Campaign '{$campaign->name}' has no active sequence with steps. Run sequence:create.");

            return self::FAILURE;
        }

        $steps = $sequence->steps;
        if ($this->option('steps')) {
            $steps = $steps->take((int) $this->option('steps'));
        }

        $leads = $campaign->leads()
            ->where('status', Lead::STATUS_VERIFIED)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No verified leads to generate for. Import and verify leads first.');

            return self::SUCCESS;
        }

        $this->info("Generating for {$leads->count()} verified lead(s) × {$steps->count()} step(s) on '{$campaign->name}'...");

        $generated = 0;
        $skipped = 0;
        $flagged = 0;

        foreach ($leads as $lead) {
            foreach ($steps as $step) {
                $exists = Message::where('lead_id', $lead->id)
                    ->where('sequence_step_id', $step->id)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                try {
                    $message = $generator->generate($lead, $campaign, $step, $campaign->product);
                    $generated++;

                    if (! empty($message->generation['spam_warnings'])) {
                        $flagged++;
                    }
                } catch (Throwable $e) {
                    $this->error("  Failed on lead #{$lead->id} step {$step->position}: {$e->getMessage()}");

                    return self::FAILURE;
                }
            }
        }

        $this->info('Done.');
        $this->line("  generated: {$generated} draft message(s)");
        $this->line("  skipped:   {$skipped} (already existed)");
        $this->line("  flagged:   {$flagged} (spam-linter warnings — check in review)");
        $this->line('  next: review with  php artisan messages:review ' . $campaign->slug);

        return self::SUCCESS;
    }
}
