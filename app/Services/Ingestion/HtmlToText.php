<?php

namespace App\Services\Ingestion;

use DOMDocument;
use DOMXPath;

/**
 * Turns an HTML document into readable text. Drops scripts, styles, and other
 * non-content nodes so the brain builder reads the page's actual copy. Shared
 * by the .html file extractor and by URL ingestion.
 */
class HtmlToText
{
    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = $this->load($html);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//script | //style | //noscript | //template | //svg | //head') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        return $body ? $body->textContent : $dom->textContent;
    }

    /**
     * The page <title>, trimmed, or null if absent.
     */
    public function title(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $titleNode = $this->load($html)->getElementsByTagName('title')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';

        return $title !== '' ? $title : null;
    }

    private function load(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        // Force UTF-8 and swallow warnings from imperfect real-world markup.
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return $dom;
    }
}
