<?php

namespace App\Console\Commands;

use App\Models\Suppression;
use App\Services\Compliance\SuppressionList;
use Illuminate\Console\Command;

class SuppressAddCommand extends Command
{
    protected $signature = 'suppress:add
        {value : Email address or domain to suppress}
        {--type=email : email or domain}
        {--reason=manual : unsubscribe | bounce | complaint | manual}';

    protected $description = 'Add an email or domain to the do-not-contact list';

    public function handle(SuppressionList $list): int
    {
        $type = $this->option('type');

        if (! in_array($type, [Suppression::TYPE_EMAIL, Suppression::TYPE_DOMAIN], true)) {
            $this->error('--type must be email or domain.');

            return self::FAILURE;
        }

        $suppression = $list->suppress($this->argument('value'), $type, $this->option('reason'));

        $this->info("Suppressed {$type} '{$suppression->value}' ({$suppression->reason}).");

        return self::SUCCESS;
    }
}
