<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class MessagesRejectCommand extends Command
{
    protected $signature = 'messages:reject
        {message : Message id to reject}
        {--note= : Reason for rejecting (recommended)}';

    protected $description = 'Reject a generated message so it will not be sent';

    public function handle(): int
    {
        $message = Message::find($this->argument('message'));

        if (! $message) {
            $this->error("Message not found: {$this->argument('message')}");

            return self::FAILURE;
        }

        $message->reject($this->option('note') ?: 'rejected');
        $this->info("Message #{$message->id} rejected.");

        if ($this->option('note')) {
            $this->line("  note: {$this->option('note')}");
        }

        return self::SUCCESS;
    }
}
