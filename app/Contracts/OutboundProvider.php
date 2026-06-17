<?php

namespace App\Contracts;

/**
 * A cold-email sending platform (Instantly, Lemlist, ...).
 *
 * OutboundEngine never sends mail itself. It hands finished sequences to
 * whichever provider is active and reads results back. Both platforms
 * implement this one contract so nothing upstream cares which is sending.
 *
 * Phase 1 ships configuration-aware stubs. The send/sync methods are added to
 * this interface in Phase 6, once there are sequences to push.
 */
interface OutboundProvider
{
    /**
     * Stable identifier, e.g. "instantly" or "lemlist".
     */
    public function name(): string;

    /**
     * True once this provider has the credentials it needs to talk to its API.
     */
    public function isConfigured(): bool;
}
