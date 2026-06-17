<?php

namespace App\Services\Ingestion;

use App\Models\Product;
use App\Models\ProductSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns a file (from a CLI path or an HTTP upload) into a stored ProductSource
 * with its text extracted. Extraction failures are recorded on the source, not
 * thrown — one bad file shouldn't sink a batch.
 */
class IngestionService
{
    public function __construct(private readonly TextExtractionManager $extractors)
    {
    }

    public function ingestFilePath(Product $product, string $absolutePath, ?string $label = null): ProductSource
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        return $this->store(
            $product,
            basename($absolutePath),
            (string) file_get_contents($absolutePath),
            $label,
        );
    }

    public function ingestUploadedFile(Product $product, UploadedFile $file, ?string $label = null): ProductSource
    {
        return $this->store(
            $product,
            $file->getClientOriginalName() ?: $file->getFilename(),
            (string) file_get_contents($file->getRealPath()),
            $label,
        );
    }

    private function store(Product $product, string $originalName, string $contents, ?string $label): ProductSource
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $stem = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';
        $storedPath = "products/{$product->id}/" . Str::random(8) . "-{$stem}" . ($extension !== '' ? ".{$extension}" : '');

        Storage::put($storedPath, $contents);
        $absolute = Storage::path($storedPath);

        $source = $product->sources()->create([
            'type' => ProductSource::TYPE_UPLOAD,
            'label' => $label,
            'original_name' => $originalName,
            'mime' => $this->detectMime($absolute),
            'path' => $storedPath,
            'bytes' => strlen($contents),
        ]);

        try {
            $source->markExtracted($this->extractors->extractFromFile($absolute));
        } catch (Throwable $e) {
            $source->markFailed($e->getMessage());
        }

        return $source->refresh();
    }

    private function detectMime(string $absolutePath): ?string
    {
        if (! function_exists('mime_content_type')) {
            return null;
        }

        return mime_content_type($absolutePath) ?: null;
    }
}
