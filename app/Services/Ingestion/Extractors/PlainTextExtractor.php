<?php

namespace App\Services\Ingestion\Extractors;

use App\Contracts\TextExtractor;
use RuntimeException;

/**
 * Plain text and Markdown. Reads the file and coerces it to UTF-8.
 */
class PlainTextExtractor implements TextExtractor
{
    public function extensions(): array
    {
        return ['txt', 'text', 'md', 'markdown'];
    }

    public function extract(string $absolutePath): string
    {
        $raw = @file_get_contents($absolutePath);

        if ($raw === false) {
            throw new RuntimeException("Could not read file: {$absolutePath}");
        }

        if ($raw !== '' && ! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return $raw;
    }
}
