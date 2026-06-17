<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Message;
use Illuminate\Console\Command;

class MessagesApproveCommand extends Command
{
    protected $signature = 'messages:approve
        {message? : Message id to approve}
        {--all : Approve all draft messages}
        {--campaign= : Scope --all to a campaign (id or slug)}
        {--note= : Optional reviewer note}';

    protected $description = 'Approve generated messages so they become eligible to send';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->approveAll();
        }

        $id = $this->argument('message');

        if (! $id) {
            $this->error('Pass a message id, or use --all.');

            return self::FAILURE;
        }

        $message = Message::find($id);

        if (! $message) {
            $this->error("Message not found: {$id}");

            return self::FAILURE;
        }

        $message->approve($this->option('note'));
        $this->info("Message #{$message->id} approved.");

        return self::SUCCESS;
    }

    private function approveAll(): int
    {
        $query = Message::where('status', Message::STATUS_DRAFT);

        if ($this->option('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->option('campaign'))
                ->orWhere('slug', $this->option('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->option('campaign')}");

                return self::FAILURE;
            }

            $query->where('campaign_id', $campaign->id);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No draft messages to approve.');

            return self::SUCCESS;
        }

        $query->get()->each(fn (Message $m) => $m->approve($this->option('note')));

        $this->info("Approved {$count} draft message(s).");

        return self::SUCCESS;
    }
}
