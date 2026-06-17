<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Sequence;
use App\Services\Personalization\VariantGenerator;
use App\Services\Settings\Settings;
use Illuminate\Console\Command;

class SequenceVariantsCommand extends Command
{
    protected $signature = 'sequence:variants
        {campaign : Campaign id or slug}
        {--step=1 : Step position to generate subject variants for}
        {--count=3 : How many variants}';

    protected $description = 'Generate A/B subject-line variants for a sequence step';

    public function handle(VariantGenerator $generator, Settings $settings): int
    {
        if (blank($settings->resolve('anthropic_api_key'))) {
            $this->error('No Anthropic key set. Add it on the settings page or in .env first.');

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
            $this->error("Campaign '{$campaign->name}' isn't linked to a product.");

            return self::FAILURE;
        }

        $sequence = $campaign->sequences()->where('status', Sequence::STATUS_ACTIVE)->latest('id')->first();

        if (! $sequence) {
            $this->error('No active sequence. Run sequence:create first.');

            return self::FAILURE;
        }

        $step = $sequence->steps()->where('position', (int) $this->option('step'))->first();

        if (! $step) {
            $this->error("No step at position {$this->option('step')}.");

            return self::FAILURE;
        }

        $count = max(1, min(8, (int) $this->option('count')));
        $subjects = $generator->subjectsForStep($step, $campaign->product, $count);

        if ($subjects === []) {
            $this->warn('The model returned no usable subject lines — try again.');

            return self::SUCCESS;
        }

        $step->update(['meta' => array_merge($step->meta ?? [], ['subject_variants' => $subjects])]);

        $this->info("Subject variants for step {$step->position} ({$step->angle}):");
        foreach ($subjects as $i => $subject) {
            $this->line('  ' . ($i + 1) . '. ' . $subject);
        }
        $this->newLine();
        $this->line('Saved to the step. A/B these in your sending platform and let the funnel show the winner.');

        return self::SUCCESS;
    }
}
