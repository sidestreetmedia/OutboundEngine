<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CampaignCreateCommand extends Command
{
    protected $signature = 'campaign:create
        {name : Campaign name}
        {--product= : Product id or slug this campaign sells}
        {--slug= : Optional slug (derived from the name otherwise)}
        {--description= : Optional description}';

    protected $description = 'Create a campaign, optionally linked to a product';

    public function handle(): int
    {
        $productId = null;

        if ($this->option('product')) {
            $product = Product::query()
                ->where('id', $this->option('product'))
                ->orWhere('slug', $this->option('product'))
                ->first();

            if (! $product) {
                $this->error("Product not found: {$this->option('product')}");

                return self::FAILURE;
            }

            $productId = $product->id;
        }

        $slug = $this->option('slug') ?: Str::slug($this->argument('name'));

        if (Campaign::where('slug', $slug)->exists()) {
            $this->error("A campaign with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $campaign = Campaign::create([
            'name' => $this->argument('name'),
            'slug' => $slug,
            'product_id' => $productId,
            'description' => $this->option('description'),
        ]);

        $this->info("Created campaign #{$campaign->id} '{$campaign->name}' (slug: {$campaign->slug}).");

        if ($productId) {
            $this->line("  selling: {$campaign->product->name}");
        }

        return self::SUCCESS;
    }
}
