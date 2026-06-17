<?php

namespace App\Contracts;

/**
 * Pulls plain text out of one family of files (PDF, docx, plain text, ...).
 * The TextExtractionManager keeps a set of these and dispatches by extension.
 */
interface TextExtractor
{
    /**
     * Lowercase file extensions this extractor handles, e.g. ['pdf'].
     *
     * @return list<string>
     */
    public function extensions(): array;

    /**
     * Return the raw text content of the file at the given absolute path.
     * Throws if the file can't be read or parsed.
     */
    public function extract(string $absolutePath): string;
}
