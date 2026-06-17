<?php

namespace App\Services\Outbound\Dto;

/**
 * Result of handing one lead to a sending platform.
 */
final class PushResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerLeadId = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(?string $providerLeadId = null): self
    {
        return new self(true, $providerLeadId);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
