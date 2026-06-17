<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Sequence;
use Illuminate\Console\Command;

class SequenceCreateCommand extends Command
{
    protected $signature = 'sequence:create
        {campaign : Campaign id or slug}
        {--name= : Sequence name}
        {--steps=3 : Number of steps (1-5)}';

    protected $description = 'Create a sequence for a campaign with sensible default steps';

    /** Always-first opener. */
    private const INTRO = [
        'angle' => 'Intro + one value prop',
        'delay_days' => 0,
        'subject_hint' => 'short, specific, lowercase, no hype',
        'instructions' => 'Open with a specific, genuine reason for reaching out tied to their role or company. Lead with one value prop. One soft CTA (e.g. "worth a quick look?"). Under 90 words. No buzzwords.',
    ];

    /** Always-last closer. */
    private const BREAKUP = [
        'angle' => 'Break-up',
        'delay_days' => 4,
        'subject_hint' => 'should I close the loop?',
        'instructions' => 'Short, low-pressure break-up. Acknowledge the timing may be off, leave the door open, give permission to say no. Under 60 words.',
    ];

    /** Filler steps between intro and break-up. */
    private const MIDDLE = [
        [
            'angle' => 'Proof / relevant example',
            'delay_days' => 3,
            'subject_hint' => 're: the thread, or a short new line',
            'instructions' => 'Lightly reference the first email. Share one concrete proof point or example relevant to their situation. Reinforce the same value prop. Soft CTA. Under 80 words.',
        ],
        [
            'angle' => 'Second angle',
            'delay_days' => 4,
            'subject_hint' => 'a different, specific hook',
            'instructions' => 'Take a different angle on the same offer — a second value prop or a different problem it solves. Stay concrete. One CTA. Under 80 words.',
        ],
        [
            'angle' => 'Quick nudge',
            'delay_days' => 4,
            'subject_hint' => 'one line',
            'instructions' => 'Very short nudge. One sentence of value, one question. Under 40 words.',
        ],
    ];

    public function handle(): int
    {
        $campaign = Campaign::query()
            ->where('id', $this->argument('campaign'))
            ->orWhere('slug', $this->argument('campaign'))
            ->first();

        if (! $campaign) {
            $this->error("Campaign not found: {$this->argument('campaign')}");

            return self::FAILURE;
        }

        $count = max(1, min(5, (int) $this->option('steps')));
        $templates = $this->templatesFor($count);

        $sequence = $campaign->sequences()->create([
            'name' => $this->option('name') ?: "{$count}-step sequence",
            'status' => Sequence::STATUS_ACTIVE,
        ]);

        foreach ($templates as $i => $template) {
            $sequence->steps()->create([
                'position' => $i + 1,
                'delay_days' => $template['delay_days'],
                'channel' => 'email',
                'angle' => $template['angle'],
                'subject_hint' => $template['subject_hint'],
                'instructions' => $template['instructions'],
            ]);
        }

        $this->info("Created sequence #{$sequence->id} '{$sequence->name}' with {$count} step(s) on '{$campaign->name}'.");

        foreach ($sequence->steps as $step) {
            $this->line("  {$step->position}. {$step->angle} (+{$step->delay_days}d)");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{angle: string, delay_days: int, subject_hint: string, instructions: string}>
     */
    private function templatesFor(int $count): array
    {
        if ($count === 1) {
            return [self::INTRO];
        }

        if ($count === 2) {
            return [self::INTRO, self::BREAKUP];
        }

        $middle = array_slice(self::MIDDLE, 0, $count - 2);

        return [self::INTRO, ...$middle, self::BREAKUP];
    }
}
