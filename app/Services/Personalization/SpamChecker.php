<?php

namespace App\Services\Personalization;

/**
 * A cheap, deterministic spam/quality linter for generated copy. It doesn't
 * block anything — it surfaces warnings onto the message so the review queue
 * can flag copy that reads like a blast instead of a human note.
 */
class SpamChecker
{
    private const RED_FLAG_PHRASES = [
        'guarantee', '100% free', 'risk-free', 'act now', 'limited time', 'buy now',
        'click here', 'cash bonus', 'no obligation', 'congratulations', 'this is not spam',
        'dear friend', 'make money', 'earn $', 'double your', 'free trial', 'special promotion',
    ];

    /**
     * @return list<string> warnings; empty means clean
     */
    public function check(string $subject, string $body): array
    {
        $warnings = [];
        $haystack = mb_strtolower($subject . "\n" . $body);

        foreach (self::RED_FLAG_PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                $warnings[] = "spammy phrase: \"{$phrase}\"";
            }
        }

        if (substr_count($subject . $body, '!') > 2) {
            $warnings[] = 'too many exclamation marks';
        }

        if (preg_match('/\b[A-Z]{4,}\b/', $subject . ' ' . $body)) {
            $warnings[] = 'shouty ALL-CAPS word';
        }

        if (mb_strlen($subject) > 70) {
            $warnings[] = 'subject longer than 70 characters';
        }

        if (str_word_count(strip_tags($body)) > 160) {
            $warnings[] = 'body longer than ~160 words';
        }

        return $warnings;
    }

    public function isClean(string $subject, string $body): bool
    {
        return $this->check($subject, $body) === [];
    }
}
