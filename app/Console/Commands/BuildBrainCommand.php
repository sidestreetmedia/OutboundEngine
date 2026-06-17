<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSource;
use App\Services\Brain\BrainBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class BuildBrainCommand extends Command
{
    protected $signature = 'product:build-brain {product : Product id or slug}';

    protected $description = "Build a product's structured profile from its ingested sources";

    public function handle(BrainBuilder $builder): int
    {
        $product = Product::query()
            ->where('id', $this->argument('product'))
            ->orWhere('slug', $this->argument('product'))
            ->first();

        if (! $product) {
            $this->error("Product not found: {$this->argument('product')}");

            return self::FAILURE;
        }

        $extracted = $product->sources()->where('status', ProductSource::STATUS_EXTRACTED)->count();
        $this->info("Building brain for '{$product->name}' from {$extracted} source(s)...");

        try {
            $profile = $builder->build($product);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Brain built. Profile summary:');
        $this->line('  what_we_do: ' . Str::limit($profile['what_we_do'] ?? '—', 100));
        $this->line('  ICPs: ' . count($profile['icp'] ?? []));
        $this->line('  differentiators: ' . count($profile['differentiators'] ?? []));
        $this->line('  problems_solved: ' . count($profile['problems_solved'] ?? []));
        $this->line('  proof_points: ' . count($profile['proof_points'] ?? []));

        return self::SUCCESS;
    }
}
