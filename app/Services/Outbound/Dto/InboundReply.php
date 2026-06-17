<?php

namespace App\Services\Outbound\Dto;

use Carbon\CarbonInterface;

/**
 * A normalized reply (or bounce) pulled back from a sending platform, shaped the
 * same way regardless of which provider it came from.
 */
final class InboundReply
{
    /**
     * @param  array<string, mixed>  $raw  The untouched provider payload.
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $subject = null,
        public readonly ?string $body = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?CarbonInterface $receivedAt = null,
        public readonly bool $isBounce = false,
        public readonly bool $isAutoReply = false,
        public readonly array $raw = [],
    ) {
    }
}
