<?php

namespace App\Services\Verification;

use App\Contracts\EmailVerifier;

/**
 * Free, no-spend email verification: address syntax plus a DNS check that the
 * domain can actually receive mail (MX, or an A record as the RFC fallback).
 * Disposable domains are flagged risky.
 *
 * This is domain-level deliverability — it catches malformed addresses and dead
 * domains before they ever cost you a send. Mailbox-level confidence (does this
 * exact inbox exist) needs a paid provider, which can layer on top later; until
 * then this is the gate, and it costs nothing.
 */
class MxVerifier implements EmailVerifier
{
    /** A few common disposable/throwaway domains — flagged risky, not invalid. */
    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', '10minutemail.com',
        'temp-mail.org', 'tempmail.com', 'throwawaymail.com', 'yopmail.com',
        'sharklasers.com', 'getnada.com', 'trashmail.com', 'maildrop.cc', 'dispostable.com',
    ];

    public function verify(string $email): string
    {
        $email = trim($email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::INVALID;
        }

        $domain = $this->domainOf($email);

        if ($domain === null) {
            return self::INVALID;
        }

        if (in_array($domain, self::DISPOSABLE, true)) {
            return self::RISKY;
        }

        return $this->domainAcceptsMail($domain) ? self::VALID : self::INVALID;
    }

    private function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = strtolower(substr($email, $at + 1));

        return $domain !== '' ? $domain : null;
    }

    /**
     * True if the domain advertises a mail exchanger, or (per RFC 5321) has an
     * A record that can serve as an implicit MX. Isolated for testability.
     */
    protected function domainAcceptsMail(string $domain): bool
    {
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
