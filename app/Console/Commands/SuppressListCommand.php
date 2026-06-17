<?php

namespace App\Console\Commands;

use App\Models\Suppression;
use Illuminate\Console\Command;

class SuppressListCommand extends Command
{
    protected $signature = 'suppress:list {--limit=50 : Max rows to show}';

    protected $description = 'Show the do-not-contact list';

    public function handle(): int
    {
        $rows = Suppression::query()
            ->orderByDesc('suppressed_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Suppression list is empty.');

            return self::SUCCESS;
        }

        $this->table(
            ['Value', 'Type', 'Reason', 'Suppressed'],
            $rows->map(fn (Suppression $s) => [
                $s->value,
                $s->type,
                $s->reason,
                $s->suppressed_at?->toDateTimeString() ?? '—',
            ]),
        );

        $this->line($rows->count() . ' shown · ' . Suppression::count() . ' total.');

        return self::SUCCESS;
    }
}
