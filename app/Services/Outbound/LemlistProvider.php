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
 * Lemlist sending platform. Auth is HTTP Basic with an empty username and the
 * API key as the password. Leads are posted to a campaign; extra fields ride
 * along as custom variables the sequence can reference. Replies come from the
 * activities feed. Endpoints follow the documented API and should be
 * smoke-tested against a real account before first live use.
 */
class LemlistProvider implements OutboundProvider
{
    private const BASE = 'https://api.lemlist.com/api';

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

    public function pushLead(string $providerCampaignId, array $lead): PushResult
    {
        if (! $this->isConfigured()) {
            return PushResult::fail('Lemlist API key not set.');
        }

        try {
            $payload = array_filter([
                'email' => $lead['email'] ?? null,
                'firstName' => $lead['first_name'] ?? null,
                'lastName' => $lead['last_name'] ?? null,
                'companyName' => $lead['company'] ?? null,
                'jobTitle' => $lead['title'] ?? null,
                'linkedinUrl' => $lead['linkedin_url'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            // Lemlist treats unknown fields as custom variables usable as {{var}}.
            foreach (($lead['variables'] ?? []) as $key => $value) {
                $payload[$key] = $value;
            }

            $response = $this->client()->post("/campaigns/{$providerCampaignId}/leads", $payload);

            if ($response->failed()) {
                return PushResult::fail("Lemlist {$response->status()}: " . $response->body());
            }

            return PushResult::ok($response->json('_id'));
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
            $response = $this->client()->get('/activities', array_filter([
                'campaignId' => $providerCampaignId,
                'type' => 'emailsReplied',
                'limit' => 100,
            ]));

            if ($response->failed()) {
                return [];
            }

            $items = $response->json() ?? [];

            return collect($items)->map(fn (array $a) => new InboundReply(
                email: $a['lead']['email'] ?? $a['email'] ?? '',
                subject: $a['subject'] ?? null,
                body: $a['text'] ?? $a['body'] ?? null,
                providerMessageId: $a['_id'] ?? null,
                receivedAt: isset($a['date']) ? Carbon::parse($a['date']) : null,
                isBounce: ($a['type'] ?? '') === 'emailsBounced',
                isAutoReply: false,
                raw: $a,
            ))->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth('', $this->apiKey)
            ->baseUrl(self::BASE)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
