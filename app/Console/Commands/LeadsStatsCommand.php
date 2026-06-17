<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;

class LeadsStatsCommand extends Command
{
    protected $signature = 'leads:stats';

    protected $description = 'Show lead counts by status and verification';

    public function handle(): int
    {
        $total = Lead::count();

        if ($total === 0) {
            $this->info('No leads yet. Import some with: php artisan leads:import path/to/list.csv');

            return self::SUCCESS;
        }

        $this->line("Total leads: <info>{$total}</info>");

        $byStatus = Lead::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $this->table(['Status', 'Count'], $byStatus->map(fn ($n, $status) => [$status, $n])->values());

        $sendable = Lead::where('status', Lead::STATUS_VERIFIED)->count();
        $this->line("Sendable (verified): <info>{$sendable}</info>");

        return self::SUCCESS;
    }
}
