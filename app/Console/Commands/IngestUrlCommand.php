<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSource;
use App\Services\Ingestion\IngestionService;
use Illuminate\Console\Command;

class IngestUrlCommand extends Command
{
    protected $signature = 'product:ingest-url
        {product : Product id or slug}
        {url : URL to fetch and ingest}
        {--label= : Optional human label for the source}';

    protected $description = "Fetch a URL and ingest its readable text into a product's brain sources";

    public function handle(IngestionService $ingestion): int
    {
        $product = Product::query()
            ->where('id', $this->argument('product'))
            ->orWhere('slug', $this->argument('product'))
            ->first();

        if (! $product) {
            $this->error("Product not found: {$this->argument('product')}");

            return self::FAILURE;
        }

        $source = $ingestion->ingestUrl($product, $this->argument('url'), $this->option('label'));

        if ($source->status === ProductSource::STATUS_EXTRACTED) {
            $label = $source->label ? " ({$source->label})" : '';
            $this->info("Ingested {$source->url}{$label} → {$source->char_count} chars (source #{$source->id}).");

            return self::SUCCESS;
        }

        $this->error("Could not ingest {$source->url}: {$source->error}");

        return self::FAILURE;
    }
}
