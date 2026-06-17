<?php

namespace App\Services\Proof;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks at what anyone can see on a prospect's public homepage and reports real,
 * observable signals — HTTPS, meta tags, mobile-friendliness, analytics, the
 * platform it's built on, social links. Nothing here is invented: every finding
 * is something present (or provably absent) in the page that was fetched. That's
 * the whole point — the proof has to be genuine.
 */
class PresenceAuditor
{
    private const SOCIAL_DOMAINS = [
        'linkedin' => 'linkedin.com',
        'facebook' => 'facebook.com',
        'instagram' => 'instagram.com',
        'twitter' => 'twitter.com',
        'x' => '//x.com',
        'youtube' => 'youtube.com',
        'tiktok' => 'tiktok.com',
    ];

    /**
     * @return array{ok: bool, url: string, findings: array<string, mixed>, error: ?string}
     */
    public function audit(string $url): array
    {
        $url = $this->normalizeUrl($url);

        if ($url === '') {
            return ['ok' => false, 'url' => '', 'findings' => [], 'error' => 'No URL to audit.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'OutboundEngine-Audit/1.0 (+public-presence audit)'])
                ->get($url);

            if ($response->failed()) {
                return ['ok' => false, 'url' => $url, 'findings' => [], 'error' => "HTTP {$response->status()}"];
            }

            return [
                'ok' => true,
                'url' => $url,
                'findings' => $this->extract($response->body(), $url),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'url' => $url, 'findings' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(string $html, string $url): array
    {
        return [
            'https' => str_starts_with($url, 'https://'),
            'title' => $this->firstMatch('/<title[^>]*>(.*?)<\/title>/is', $html),
            'meta_description' => $this->metaContent('description', $html),
            'has_meta_description' => $this->metaContent('description', $html) !== null,
            'mobile_viewport' => (bool) preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html),
            'has_h1' => (bool) preg_match('/<h1[\s>]/i', $html),
            'open_graph' => (bool) preg_match('/property=["\']og:/i', $html),
            'favicon' => (bool) preg_match('/<link[^>]+rel=["\'][^"\']*icon/i', $html),
            'analytics' => $this->containsAny($html, ['googletagmanager.com', 'gtag(', 'google-analytics.com', "ga('create'"]),
            'tracking_pixel' => $this->containsAny($html, ['connect.facebook.net', 'fbq(']),
            'platform' => $this->platform($html),
            'social_links' => $this->socialLinks($html),
            'structured_data' => stripos($html, 'application/ld+json') !== false,
            'html_bytes' => strlen($html),
        ];
    }

    private function metaContent(string $name, string $html): ?string
    {
        // name then content
        $value = $this->firstMatch(
            '/<meta[^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]+content=["\'](.*?)["\']/is',
            $html,
        );

        if ($value !== null) {
            return $value;
        }

        // content then name
        return $this->firstMatch(
            '/<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']' . preg_quote($name, '/') . '["\']/is',
            $html,
        );
    }

    private function platform(string $html): ?string
    {
        $generator = $this->firstMatch('/<meta[^>]+name=["\']generator["\'][^>]+content=["\'](.*?)["\']/is', $html);

        if ($generator) {
            return $generator;
        }

        $hints = [
            'WordPress' => ['wp-content', 'wp-includes'],
            'Wix' => ['wix.com', 'wixstatic'],
            'Squarespace' => ['squarespace.com', 'static1.squarespace'],
            'Shopify' => ['cdn.shopify.com', 'myshopify.com'],
            'Webflow' => ['webflow.io', 'assets.website-files.com'],
        ];

        foreach ($hints as $name => $needles) {
            if ($this->containsAny($html, $needles)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function socialLinks(string $html): array
    {
        $found = [];

        foreach (self::SOCIAL_DOMAINS as $label => $needle) {
            if (stripos($html, $needle) !== false) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function firstMatch(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $m) && isset($m[1])) {
            $value = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }
}
