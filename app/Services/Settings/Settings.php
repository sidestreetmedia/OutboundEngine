<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Runtime settings with precedence: a value saved here (settings page or CLI)
 * overrides the matching .env value. Secrets are encrypted at rest. If the
 * settings table doesn't exist yet (fresh install, pre-migration), everything
 * falls back to env so the app still boots.
 *
 * @var array<string, array{label: string, fallback: string, secret: bool, group: string, placeholder?: string}>
 */
class Settings
{
    public const DEFINITIONS = [
        'anthropic_api_key' => ['label' => 'Anthropic API key', 'fallback' => 'outbound.llm.key', 'secret' => true, 'group' => 'AI', 'placeholder' => 'sk-ant-...'],
        'llm_model' => ['label' => 'LLM model', 'fallback' => 'outbound.llm.model', 'secret' => false, 'group' => 'AI', 'placeholder' => 'claude-sonnet-4-6'],
        'llm_provider' => ['label' => 'LLM provider', 'fallback' => 'outbound.llm.provider', 'secret' => false, 'group' => 'AI', 'placeholder' => 'anthropic or google'],
        'google_api_key' => ['label' => 'Google AI (Gemma) API key', 'fallback' => 'outbound.llm.google_key', 'secret' => true, 'group' => 'AI', 'placeholder' => 'AIza... — free from Google AI Studio'],
        'outbound_provider' => ['label' => 'Default sending platform', 'fallback' => 'outbound.provider', 'secret' => false, 'group' => 'Sending', 'placeholder' => 'instantly'],
        'instantly_api_key' => ['label' => 'Instantly API key', 'fallback' => 'outbound.providers.instantly.key', 'secret' => true, 'group' => 'Sending'],
        'lemlist_api_key' => ['label' => 'Lemlist API key', 'fallback' => 'outbound.providers.lemlist.key', 'secret' => true, 'group' => 'Sending'],
        'verify_provider' => ['label' => 'Email verification provider', 'fallback' => 'outbound.verification.provider', 'secret' => false, 'group' => 'Verification'],
        'verify_api_key' => ['label' => 'Email verification API key', 'fallback' => 'outbound.verification.key', 'secret' => true, 'group' => 'Verification'],
        'apollo_api_key' => ['label' => 'Apollo API key', 'fallback' => 'outbound.apollo.key', 'secret' => true, 'group' => 'Enrichment'],
        'hubspot_api_key' => ['label' => 'HubSpot private-app token', 'fallback' => 'outbound.hubspot.key', 'secret' => true, 'group' => 'CRM', 'placeholder' => 'pat-na1-...'],
        'hubspot_notify_email' => ['label' => 'Email summary to (on add)', 'fallback' => 'outbound.hubspot.notify_email', 'secret' => false, 'group' => 'CRM', 'placeholder' => 'leave blank to turn off'],
        'hubspot_portal_id' => ['label' => 'HubSpot portal ID (optional)', 'fallback' => 'outbound.hubspot.portal_id', 'secret' => false, 'group' => 'CRM', 'placeholder' => 'your HubSpot account id — links the contact in the email'],
    ];

    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    /**
     * The effective value: saved override if present, otherwise the env-backed
     * config fallback.
     */
    public function resolve(string $key): mixed
    {
        $stored = $this->stored($key);

        if ($stored !== null) {
            return $stored;
        }

        $fallback = self::DEFINITIONS[$key]['fallback'] ?? null;

        return $fallback ? config($fallback) : null;
    }

    /**
     * The saved override value (decrypted if secret), or null if not set.
     */
    public function stored(string $key): ?string
    {
        $raw = $this->raw()[$key] ?? null;

        if ($raw === null) {
            return null;
        }

        if ($this->isSecret($key)) {
            try {
                return Crypt::decryptString($raw);
            } catch (Throwable) {
                return null;
            }
        }

        return $raw;
    }

    public function set(string $key, ?string $value): void
    {
        $value = ($value === '' ? null : $value);

        $stored = ($value !== null && $this->isSecret($key))
            ? Crypt::encryptString($value)
            : $value;

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_secret' => $this->isSecret($key)],
        );

        $this->cache = null;
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->cache = null;
    }

    public function isSecret(string $key): bool
    {
        return (bool) (self::DEFINITIONS[$key]['secret'] ?? false);
    }

    public function isOverridden(string $key): bool
    {
        return $this->stored($key) !== null;
    }

    /**
     * One row per definition for the settings page / CLI: where the value comes
     * from and a safe preview (never the raw secret).
     *
     * @return list<array{key: string, label: string, group: string, secret: bool, placeholder: string, source: string, preview: string, is_set: bool}>
     */
    public function overview(): array
    {
        $rows = [];

        foreach (self::DEFINITIONS as $key => $def) {
            $saved = $this->isOverridden($key);
            $effective = $this->resolve($key);
            $isSet = filled($effective);

            $rows[] = [
                'key' => $key,
                'label' => $def['label'],
                'group' => $def['group'],
                'secret' => $def['secret'],
                'placeholder' => $def['placeholder'] ?? '',
                'source' => $saved ? 'saved' : ($isSet ? 'env' : 'unset'),
                'preview' => $this->preview($key, $effective, $def['secret']),
                'is_set' => $isSet,
            ];
        }

        return $rows;
    }

    private function preview(string $key, mixed $value, bool $secret): string
    {
        if (blank($value)) {
            return '';
        }

        if (! $secret) {
            return (string) $value;
        }

        $value = (string) $value;
        $tail = substr($value, -4);

        return '••••' . $tail;
    }

    /**
     * Raw stored values keyed by setting key, loaded once. Falls back to an
     * empty set if the table isn't available yet.
     *
     * @return array<string, string|null>
     */
    private function raw(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = Setting::pluck('value', 'key')->all();
        } catch (Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }
}
