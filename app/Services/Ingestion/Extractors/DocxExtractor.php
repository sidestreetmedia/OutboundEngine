<?php

namespace App\Services\Ingestion\Extractors;

use App\Contracts\TextExtractor;
use DOMDocument;
use RuntimeException;
use ZipArchive;

/**
 * Word .docx text. A .docx is a zip; the body lives in word/document.xml as
 * <w:t> runs inside <w:p> paragraphs. We pull each paragraph onto its own line.
 * No PhpWord dependency — ZipArchive + DOMDocument is enough for text.
 */
class DocxExtractor implements TextExtractor
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function extensions(): array
    {
        return ['docx'];
    }

    public function extract(string $absolutePath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Could not open .docx archive: {$absolutePath}");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('No word/document.xml inside the file — not a valid .docx?');
        }

        $dom = new DOMDocument();

        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('Could not parse word/document.xml.');
        }

        $lines = [];
        foreach ($dom->getElementsByTagNameNS(self::NS, 'p') as $paragraph) {
            $text = '';
            foreach ($paragraph->getElementsByTagNameNS(self::NS, 't') as $run) {
                $text .= $run->textContent;
            }
            $lines[] = $text;
        }

        return implode("\n", $lines);
    }
}
