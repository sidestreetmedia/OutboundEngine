<?php

namespace App\Services\Outbound;

use App\Contracts\OutboundProvider;
use App\Services\Outbound\Dto\InboundReply;
use App\Services\Outbound\Dto\PushResult;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Instantly sending platform (API v2). Auth is a Bearer token; leads are added
 * to a campaign with their personalized copy carried in custom_variables, which
 * the campaign's sequence references. Endpoints follow the documented v2 API and
 * should be smoke-tested against a real workspace before first live use.
 */
class InstantlyProvider implements OutboundProvider
{
    private const BASE = 'https://api.instantly.ai/api/v2';

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

    public function pushLead(string $providerCampaignId, array $lead): PushResult
    {
        if (! $this->isConfigured()) {
            return PushResult::fail('Instantly API key not set.');
        }

        try {
            $response = $this->client()->post('/leads', array_filter([
                'campaign' => $providerCampaignId,
                'email' => $lead['email'] ?? null,
                'first_name' => $lead['first_name'] ?? null,
                'last_name' => $lead['last_name'] ?? null,
                'company_name' => $lead['company'] ?? null,
                'company_domain' => $lead['company_domain'] ?? null,
                'custom_variables' => $lead['variables'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '' && $v !== []));

            if ($response->failed()) {
                return PushResult::fail("Instantly {$response->status()}: " . $response->body());
            }

            return PushResult::ok($response->json('id'));
        } catch (Throwable $e) {
            return PushResult::fail($e->getMessage());
        }
    }

    public function fetchReplies(string $providerCampaignId, ?CarbonInterface $since = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            // Received emails on the campaign are the replies. ue_type marks the
            // kind of email; bounces/auto-replies are flagged on the record.
            $response = $this->client()->get('/emails', array_filter([
                'campaign_id' => $providerCampaignId,
                'email_type' => 'received',
                'limit' => 100,
            ]));

            if ($response->failed()) {
                return [];
            }

            $items = $response->json('items') ?? $response->json('data') ?? [];

            return collect($items)->map(fn (array $e) => new InboundReply(
                email: $e['lead'] ?? $e['from_address_email'] ?? '',
                subject: $e['subject'] ?? null,
                body: $e['body']['text'] ?? null,
                providerMessageId: $e['id'] ?? $e['message_id'] ?? null,
                receivedAt: isset($e['timestamp_email']) ? Carbon::parse($e['timestamp_email']) : null,
                isBounce: (bool) ($e['is_bounce'] ?? false),
                isAutoReply: (bool) ($e['is_auto_reply'] ?? false),
                raw: $e,
            ))->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->baseUrl(self::BASE)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
