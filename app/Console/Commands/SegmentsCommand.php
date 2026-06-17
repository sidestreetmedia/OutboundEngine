<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Analytics\SegmentPerformance;
use Illuminate\Console\Command;

class SegmentsCommand extends Command
{
    protected $signature = 'segments
        {campaign? : Campaign id or slug (overall if omitted)}
        {--by=value_prop : value_prop | angle | title | industry}';

    protected $description = 'Rank segments by positive-reply rate to see what is working';

    public function handle(SegmentPerformance $performance): int
    {
        $campaignId = null;

        if ($this->argument('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->argument('campaign'))
                ->orWhere('slug', $this->argument('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->argument('campaign')}");

                return self::FAILURE;
            }

            $campaignId = $campaign->id;
        }

        $by = $this->option('by');
        $method = match ($by) {
            'value_prop' => 'byValueProp',
            'angle' => 'byAngle',
            'title' => 'byTitle',
            'industry' => 'byIndustry',
            default => null,
        };

        if ($method === null) {
            $this->error('--by must be one of: value_prop, angle, title, industry');

            return self::FAILURE;
        }

        $rows = $performance->{$method}($campaignId);

        if ($rows === []) {
            $this->info('No contacted leads to analyze yet. Push a campaign first.');

            return self::SUCCESS;
        }

        $this->info("Segment performance by {$by} — best positive-reply rate first:");
        $this->table(
            [ucfirst(str_replace('_', ' ', $by)), 'Sent', 'Positive', 'Rate'],
            array_map(fn (array $r) => [
                $r['segment'],
                $r['sent'],
                $r['positive'],
                "{$r['positive_rate']}%",
            ], $rows),
        );

        $this->line('Tip: lean into the top rows; rewrite or retire the bottom ones.');

        return self::SUCCESS;
    }
}
