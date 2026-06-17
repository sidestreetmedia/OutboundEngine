<?php

namespace App\Services\Ingestion;

use App\Contracts\TextExtractor;
use RuntimeException;

/**
 * Holds the registered extractors and routes a file to the right one by
 * extension, then normalizes the result into clean text the LLM can chew on.
 */
class TextExtractionManager
{
    /**
     * @param  list<TextExtractor>  $extractors
     */
    public function __construct(private readonly array $extractors)
    {
    }

    public function supports(string $extension): bool
    {
        return $this->extractorFor(strtolower($extension)) !== null;
    }

    /**
     * @return list<string>
     */
    public function supportedExtensions(): array
    {
        $all = [];
        foreach ($this->extractors as $extractor) {
            foreach ($extractor->extensions() as $ext) {
                $all[] = $ext;
            }
        }
        sort($all);

        return array_values(array_unique($all));
    }

    public function extractFromFile(string $absolutePath): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $extractor = $this->extractorFor($extension);

        if ($extractor === null) {
            throw new RuntimeException(
                "No text extractor for '.{$extension}' files. Supported: " . implode(', ', $this->supportedExtensions())
            );
        }

        return $this->normalize($extractor->extract($absolutePath));
    }

    private function extractorFor(string $extension): ?TextExtractor
    {
        foreach ($this->extractors as $extractor) {
            if (in_array($extension, $extractor->extensions(), true)) {
                return $extractor;
            }
        }

        return null;
    }

    /**
     * Collapse whitespace and strip control characters while keeping paragraph
     * breaks intact, so the brain builder sees readable prose, not noise.
     */
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Drop control chars except newline and tab.
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? $text;
        // Runs of spaces/tabs become a single space.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        // Trim trailing spaces before newlines.
        $text = preg_replace('/ *\n/', "\n", $text) ?? $text;
        // Three or more blank lines collapse to one blank line.
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
