<?php

namespace App\Services\Outbound;

use App\Contracts\OutboundProvider;

/**
 * Lemlist sending platform. Same contract as Instantly so nothing upstream
 * cares which is active. API calls land in Phase 6.
 */
class LemlistProvider implements OutboundProvider
{
    public function __construct(private readonly ?string $apiKey = null)
    {
    }

    public function name(): string
    {
        return 'lemlist';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }
}
