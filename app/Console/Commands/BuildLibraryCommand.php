<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Brain\LibraryBuilder;
use Illuminate\Console\Command;
use Throwable;

class BuildLibraryCommand extends Command
{
    protected $signature = 'product:build-library {product : Product id or slug}';

    protected $description = "Derive a product's personas and value-prop library from its profile";

    public function handle(LibraryBuilder $builder): int
    {
        $product = Product::query()
            ->where('id', $this->argument('product'))
            ->orWhere('slug', $this->argument('product'))
            ->first();

        if (! $product) {
            $this->error("Product not found: {$this->argument('product')}");

            return self::FAILURE;
        }

        $this->info("Building persona + value-prop library for '{$product->name}'...");

        try {
            $builder->build($product);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $product->load(['personas', 'valueProps']);
        $mapped = $product->valueProps->whereNotNull('persona_id')->count();
        $agnostic = $product->valueProps->whereNull('persona_id')->count();

        $this->info('Library built:');
        $this->line('  personas: ' . $product->personas->count());
        $this->line("  value props: {$product->valueProps->count()} ({$mapped} persona-mapped, {$agnostic} company-level)");

        foreach ($product->personas as $persona) {
            $this->line("    • {$persona->name} ({$persona->role})");
        }

        return self::SUCCESS;
    }
}
