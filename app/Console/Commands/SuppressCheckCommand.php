<?php

namespace App\Console\Commands;

use App\Services\Compliance\SuppressionList;
use Illuminate\Console\Command;

class SuppressCheckCommand extends Command
{
    protected $signature = 'suppress:check {email : Email address to check}';

    protected $description = 'Check whether an address is on the do-not-contact list';

    public function handle(SuppressionList $list): int
    {
        $email = $this->argument('email');

        if ($list->isSuppressed($email)) {
            $this->warn("SUPPRESSED — do not contact: {$email}");
        } else {
            $this->info("OK to contact: {$email}");
        }

        return self::SUCCESS;
    }
}
