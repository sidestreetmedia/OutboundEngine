<?php

namespace App\Services\Verification;

use App\Contracts\EmailVerifier;

/**
 * Placeholder verifier so the container resolves cleanly in Phase 1.
 * Verifies nothing — every address comes back "unknown". A real provider
 * is wired in Phase 3 (Lead Pipeline), where bounces start to matter.
 */
class NullVerifier implements EmailVerifier
{
    public function verify(string $email): string
    {
        return self::UNKNOWN;
    }
}
