<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Analytics\FunnelMetrics;
use Illuminate\Console\Command;

class DashboardCommand extends Command
{
    protected $signature = 'dashboard {campaign? : Campaign id or slug (overall if omitted)}';

    protected $description = 'Print the funnel: leads → pushed → replies → positive, with rates and cost';

    public function handle(FunnelMetrics $metrics): int
    {
        if ($this->argument('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->argument('campaign'))
                ->orWhere('slug', $this->argument('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->argument('campaign')}");

                return self::FAILURE;
            }

            $data = $metrics->forCampaign($campaign);
        } else {
            $data = $metrics->overall();
        }

        $this->newLine();
        $this->line("  <info>{$data['scope']}</info> — funnel");
        $this->newLine();

        $labels = [
            'leads' => 'Leads',
            'verified' => 'Verified',
            'generated' => 'Generated',
            'approved' => 'Approved',
            'pushed' => 'Pushed',
            'replied' => 'Replied',
            'positive' => 'Positive',
        ];
        $denom = max(1, $data['funnel']['leads']);

        foreach ($labels as $key => $label) {
            $count = $data['funnel'][$key];
            $bar = str_repeat('█', (int) round($count / $denom * 24));
            $this->line(sprintf('  %-10s %4d  %s', $label, $count, $bar));
        }

        $this->newLine();
        $this->line("  positive reply rate: <info>{$data['rates']['positive_reply_rate']}%</info>  ·  reply rate: {$data['rates']['reply_rate']}%");
        $this->line("  suppressed (do-not-contact): {$data['funnel']['suppressed']}");

        if ($data['replies']['total'] > 0) {
            $parts = [];
            foreach ($data['replies']['by_class'] as $class => $count) {
                $parts[] = "{$class} {$count}";
            }
            $this->line('  replies: ' . implode(' · ', $parts));
        }

        $this->line('  spend: $' . number_format($data['cost']['total_usd'], 4));
        $this->newLine();

        return self::SUCCESS;
    }
}
