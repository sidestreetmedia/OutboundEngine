<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

class CampaignListCommand extends Command
{
    protected $signature = 'campaign:list';

    protected $description = 'List campaigns with product, lead and sequence counts';

    public function handle(): int
    {
        $campaigns = Campaign::query()
            ->with('product')
            ->withCount(['leads', 'sequences'])
            ->orderBy('id')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No campaigns yet. Create one with: php artisan campaign:create "Name" --product=slug');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Status', 'Product', 'Leads', 'Sequences'],
            $campaigns->map(fn (Campaign $c) => [
                $c->id,
                $c->name,
                $c->slug,
                $c->status,
                $c->product?->name ?? '—',
                $c->leads_count,
                $c->sequences_count,
            ]),
        );

        return self::SUCCESS;
    }
}
