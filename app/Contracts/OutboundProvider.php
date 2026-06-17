<?php

namespace App\Contracts;

use App\Services\Outbound\Dto\InboundReply;
use App\Services\Outbound\Dto\PushResult;
use Carbon\CarbonInterface;

/**
 * A cold-email sending platform (Instantly, Lemlist, ...).
 *
 * OutboundEngine never sends mail itself. It hands finished, approved copy to
 * whichever provider is active and reads results back. Both platforms implement
 * this one contract so nothing upstream cares which is sending.
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

    /**
     * Add one lead (with its personalized copy carried as variables) to a
     * provider-side campaign. The campaign itself — sending accounts, schedule,
     * and a sequence that references those variables — is configured on the
     * platform; OutboundEngine feeds it.
     *
     * @param  array{
     *     email: string,
     *     first_name?: ?string,
     *     last_name?: ?string,
     *     company?: ?string,
     *     company_domain?: ?string,
     *     title?: ?string,
     *     linkedin_url?: ?string,
     *     variables?: array<string, string>
     * }  $lead
     */
    public function pushLead(string $providerCampaignId, array $lead): PushResult;

    /**
     * Pull replies (and bounces) back for a provider campaign, optionally only
     * those after a given time.
     *
     * @return list<InboundReply>
     */
    public function fetchReplies(string $providerCampaignId, ?CarbonInterface $since = null): array;
}
