<?php

namespace App\Console\Commands;

use App\Services\Settings\Settings;
use Illuminate\Console\Command;

class SettingsSetCommand extends Command
{
    protected $signature = 'settings:set {key : Setting key} {value : Value (use "" to clear)}';

    protected $description = 'Save a setting (overrides .env); secrets are encrypted at rest';

    public function handle(Settings $settings): int
    {
        $key = $this->argument('key');

        if (! array_key_exists($key, Settings::DEFINITIONS)) {
            $this->error("Unknown setting '{$key}'. Known keys: " . implode(', ', array_keys(Settings::DEFINITIONS)));

            return self::FAILURE;
        }

        $value = $this->argument('value');

        if ($value === '') {
            $settings->forget($key);
            $this->info("Cleared '{$key}' (falls back to .env if present).");

            return self::SUCCESS;
        }

        $settings->set($key, $value);
        $shown = $settings->isSecret($key) ? '••••' . substr($value, -4) : $value;
        $this->info("Saved '{$key}' = {$shown}.");

        return self::SUCCESS;
    }
}
