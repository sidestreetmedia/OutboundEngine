<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProductCreateCommand extends Command
{
    protected $signature = 'product:create
        {name : Product name}
        {--slug= : Optional slug (derived from the name otherwise)}
        {--one-liner= : Short description}';

    protected $description = 'Create a product';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        if (Product::where('slug', $slug)->exists()) {
            $this->error("A product with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $product = Product::create([
            'name' => $name,
            'slug' => $slug,
            'one_liner' => $this->option('one-liner'),
        ]);

        $this->info("Created product #{$product->id} '{$product->name}' (slug: {$product->slug}).");

        return self::SUCCESS;
    }
}
