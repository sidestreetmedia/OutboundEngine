<?php

namespace App\Services\Outbound;

use App\Contracts\OutboundProvider;
use InvalidArgumentException;

/**
 * Keeps the configured sending platforms and hands back whichever one is
 * asked for. The default comes from config('outbound.provider'). Upstream code
 * depends on the OutboundProvider contract and lets this pick the concrete one,
 * so swapping Instantly for Lemlist is a config change, not a code change.
 */
class OutboundManager
{
    /**
     * @param  array<string, OutboundProvider>  $providers  Keyed by provider name.
     */
    public function __construct(
        private readonly array $providers,
        private readonly string $default,
    ) {
    }

    /**
     * Resolve a provider by name, or the configured default when null.
     */
    public function driver(?string $name = null): OutboundProvider
    {
        $name ??= $this->default;

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown outbound provider [{$name}].");
        }

        return $this->providers[$name];
    }

    /**
     * @return array<string, OutboundProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function defaultName(): string
    {
        return $this->default;
    }
}
