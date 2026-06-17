<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ProductListCommand extends Command
{
    protected $signature = 'product:list';

    protected $description = 'List products with source counts and brain status';

    public function handle(): int
    {
        $products = Product::withCount('sources')->orderBy('id')->get();

        if ($products->isEmpty()) {
            $this->info('No products yet. Create one with: php artisan product:create "Name"');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Sources', 'Brain'],
            $products->map(fn (Product $p) => [
                $p->id,
                $p->name,
                $p->slug,
                $p->sources_count,
                $p->hasBrain() ? 'built ' . $p->brain_built_at->diffForHumans() : 'no brain',
            ]),
        );

        return self::SUCCESS;
    }
}
