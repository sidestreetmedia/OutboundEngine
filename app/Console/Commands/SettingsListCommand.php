<?php

namespace App\Console\Commands;

use App\Services\Settings\Settings;
use Illuminate\Console\Command;

class SettingsListCommand extends Command
{
    protected $signature = 'settings:list';

    protected $description = 'List settings, where each value comes from, and a safe preview';

    public function handle(Settings $settings): int
    {
        $rows = collect($settings->overview())->map(fn (array $row) => [
            $row['key'],
            $row['group'],
            $row['source'],
            $row['preview'] !== '' ? $row['preview'] : '—',
        ]);

        $this->table(['Key', 'Group', 'Source', 'Value'], $rows);
        $this->line('Source: <info>saved</info> = entered here · <comment>env</comment> = from .env · unset = not configured');

        return self::SUCCESS;
    }
}
