<?php

namespace App\Services\Outbound;

use App\Contracts\OutboundProvider;

/**
 * Instantly sending platform. Phase 1 ships only credential awareness;
 * the actual API calls (push sequence, pull replies/bounces) land in Phase 6.
 */
class InstantlyProvider implements OutboundProvider
{
    public function __construct(private readonly ?string $apiKey = null)
    {
    }

    public function name(): string
    {
        return 'instantly';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }
}
