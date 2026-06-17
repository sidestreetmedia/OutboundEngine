<?php

namespace App\Services\Ingestion\Extractors;

use App\Contracts\TextExtractor;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * PDF text via smalot/pdfparser (pure PHP — no system binary needed).
 * Image-only / scanned PDFs yield little or no text; that's surfaced upstream
 * as a near-empty extraction rather than an error.
 */
class PdfExtractor implements TextExtractor
{
    public function extensions(): array
    {
        return ['pdf'];
    }

    public function extract(string $absolutePath): string
    {
        try {
            $document = (new Parser())->parseFile($absolutePath);

            return $document->getText();
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to parse PDF: {$e->getMessage()}", 0, $e);
        }
    }
}
