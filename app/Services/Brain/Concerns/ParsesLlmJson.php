<?php

namespace App\Services\Brain\Concerns;

use RuntimeException;

/**
 * Pulls a JSON object out of an LLM reply, tolerating code fences or stray
 * prose around it. Shared by the brain and library builders.
 */
trait ParsesLlmJson
{
    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('The model did not return a JSON object.');
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($data)) {
            throw new RuntimeException('The model returned invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }
}
