<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSource;
use App\Services\Ingestion\IngestionService;
use Illuminate\Console\Command;

class IngestSourceCommand extends Command
{
    protected $signature = 'product:ingest
        {product : Product id or slug}
        {file : Absolute path to a PDF, .docx, or text file}
        {--label= : Optional human label for the source}';

    protected $description = "Ingest a file into a product's brain sources and extract its text";

    public function handle(IngestionService $ingestion): int
    {
        $product = $this->resolveProduct($this->argument('product'));

        if (! $product) {
            $this->error("Product not found: {$this->argument('product')}");

            return self::FAILURE;
        }

        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $source = $ingestion->ingestFilePath($product, $file, $this->option('label'));

        if ($source->status === ProductSource::STATUS_EXTRACTED) {
            $this->info("Ingested '{$source->original_name}' → {$source->char_count} chars (source #{$source->id}).");

            return self::SUCCESS;
        }

        $this->error("Stored '{$source->original_name}' but extraction failed: {$source->error}");

        return self::FAILURE;
    }

    private function resolveProduct(string $key): ?Product
    {
        return Product::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->first();
    }
}
