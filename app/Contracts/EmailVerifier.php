<?php

namespace App\Contracts;

/**
 * Checks whether an address is safe to send to. Bounces wreck sender
 * reputation, so every lead passes through this before it is ever contacted.
 *
 * Phase 1 binds this to NullVerifier (returns "unknown" — verifies nothing).
 * A real provider is wired in Phase 3 (Lead Pipeline).
 */
interface EmailVerifier
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const RISKY = 'risky';
    public const UNKNOWN = 'unknown';

    /**
     * @return string One of the VALID|INVALID|RISKY|UNKNOWN constants.
     */
    public function verify(string $email): string;
}
