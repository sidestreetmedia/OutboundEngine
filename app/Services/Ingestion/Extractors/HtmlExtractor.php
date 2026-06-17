<?php

namespace App\Services\Ingestion\Extractors;

use App\Contracts\TextExtractor;
use App\Services\Ingestion\HtmlToText;
use RuntimeException;

/**
 * Saved HTML files (.html/.htm). Same conversion path as URL ingestion.
 */
class HtmlExtractor implements TextExtractor
{
    public function __construct(private readonly HtmlToText $htmlToText)
    {
    }

    public function extensions(): array
    {
        return ['html', 'htm'];
    }

    public function extract(string $absolutePath): string
    {
        $html = @file_get_contents($absolutePath);

        if ($html === false) {
            throw new RuntimeException("Could not read file: {$absolutePath}");
        }

        return $this->htmlToText->convert($html);
    }
}
