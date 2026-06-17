<?php

namespace App\Services\Ingestion;

use App\Models\Product;
use App\Models\ProductSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
    public function __construct(
        private readonly TextExtractionManager $extractors,
        private readonly HtmlToText $htmlToText,
    ) {
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

    /**
     * Fetch a URL, store the raw HTML, and extract readable text. Network and
     * parse failures are recorded on the source rather than thrown.
     */
    public function ingestUrl(Product $product, string $url, ?string $label = null): ProductSource
    {
        $source = $product->sources()->create([
            'type' => ProductSource::TYPE_URL,
            'label' => $label,
            'url' => $url,
        ]);

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'OutboundEngine/1.0 (+brain ingest)'])
                ->get($url);

            if ($response->failed()) {
                $source->markFailed("Fetch failed: HTTP {$response->status()}");

                return $source->refresh();
            }

            $html = $response->body();
            $storedPath = "products/{$product->id}/" . Str::random(8) . '-url.html';
            Storage::put($storedPath, $html);

            $source->forceFill([
                'mime' => 'text/html',
                'path' => $storedPath,
                'bytes' => strlen($html),
                'label' => $label ?: $this->htmlToText->title($html),
            ])->save();

            $source->markExtracted($this->extractors->normalize($this->htmlToText->convert($html)));
        } catch (Throwable $e) {
            $source->markFailed("Fetch error: {$e->getMessage()}");
        }

        return $source->refresh();
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
