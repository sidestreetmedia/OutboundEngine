<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Message;
use Illuminate\Console\Command;

class MessagesReviewCommand extends Command
{
    protected $signature = 'messages:review
        {campaign? : Campaign id or slug to scope to}
        {--status=draft : Message status to review}
        {--limit=20 : Max messages to show}';

    protected $description = 'Review generated messages before they are eligible to send';

    public function handle(): int
    {
        $query = Message::query()
            ->with(['lead', 'valueProp'])
            ->where('status', $this->option('status'))
            ->orderBy('lead_id')
            ->orderBy('position')
            ->limit((int) $this->option('limit'));

        if ($this->argument('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->argument('campaign'))
                ->orWhere('slug', $this->argument('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->argument('campaign')}");

                return self::FAILURE;
            }

            $query->where('campaign_id', $campaign->id);
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            $this->info("No {$this->option('status')} messages.");

            return self::SUCCESS;
        }

        foreach ($messages as $message) {
            $this->line(str_repeat('─', 64));
            $this->line("<info>Message #{$message->id}</info> · {$message->lead?->email} · step {$message->position} ({$message->generation['angle']})");
            $this->line('value prop: ' . ($message->valueProp?->headline ?? '—'));

            $warnings = $message->generation['spam_warnings'] ?? [];
            if (! empty($warnings)) {
                $this->line('<comment>⚠ ' . implode('; ', $warnings) . '</comment>');
            }

            $this->newLine();
            $this->line("Subject: {$message->subject}");
            $this->newLine();
            $this->line($message->body);
            $this->newLine();
        }

        $this->line(str_repeat('─', 64));
        $this->line("{$messages->count()} {$this->option('status')} message(s).");
        $this->line('  approve:  php artisan messages:approve {id}   (or --all [--campaign=slug])');
        $this->line('  reject:   php artisan messages:reject {id} --note="why"');

        return self::SUCCESS;
    }
}
