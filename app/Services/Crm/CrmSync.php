<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\Reply;

/**
 * The single place that turns a positive-reply lead into a HubSpot contact.
 * Both the hubspot:push command and the per-contact toggle on the wins page call
 * pushLead(), so the behaviour — what gets written, the CTA note, idempotency —
 * stays identical no matter where the push is triggered from.
 */
class CrmSync
{
    public function __construct(private readonly HubspotClient $hubspot)
    {
    }

    public function isReady(): bool
    {
        return $this->hubspot->isConfigured();
    }

    /**
     * Upsert the lead as a HubSpot contact (keyed on email) and attach a note
     * capturing the campaign + CTA they responded to.
     *
     * @return array{ok: bool, contact_id: ?string, noted: bool, skipped: ?string, error: ?string}
     */
    public function pushLead(Lead $lead): array
    {
        if (! $this->hubspot->isConfigured()) {
            return $this->fail('HubSpot token not set.');
        }

        if (! $lead->email || str_contains($lead->email, '@unenriched.invalid')) {
            return ['ok' => false, 'contact_id' => null, 'noted' => false, 'skipped' => 'no real email yet (enrich first)', 'error' => null];
        }

        $properties = array_filter([
            'email' => $lead->email,
            'firstname' => $lead->first_name,
            'lastname' => $lead->last_name,
            'jobtitle' => $lead->title,
            'company' => $lead->company,
            'website' => $lead->company_domain,
        ], fn ($v) => filled($v));

        $contact = $this->hubspot->upsertContact($properties);

        if (! $contact['ok']) {
            return $this->fail($contact['error']);
        }

        $contactId = $contact['id'];
        $noted = false;

        if ($contactId) {
            $note = $this->hubspot->addNote($contactId, $this->noteBody($lead));
            $noted = $note['ok'];
        }

        $lead->hubspot_contact_id = $contactId;
        $lead->hubspot_synced_at = now();
        $lead->save();

        return ['ok' => true, 'contact_id' => $contactId, 'noted' => $noted, 'skipped' => null, 'error' => null];
    }

    private function noteBody(Lead $lead): string
    {
        $campaign = $lead->campaign?->name ?: 'OutboundEngine';
        $reply = $lead->replies()
            ->where('classification', Reply::CLASS_INTERESTED)
            ->latest('received_at')
            ->first();

        $lines = [
            'Responded positively via OutboundEngine.',
            "Campaign: {$campaign}",
            'Offer / CTA: ' . $this->offer($lead),
        ];

        if ($reply && filled($reply->body)) {
            $snippet = trim(mb_substr((string) $reply->body, 0, 240));
            $lines[] = "Their reply: \"{$snippet}\"";
        }

        $who = trim(($lead->title ?: '') . ($lead->company ? " at {$lead->company}" : ''));
        if ($who !== '') {
            $lines[] = $who;
        }

        return implode("\n", $lines);
    }

    /**
     * The CTA they actually got: pulled from the sent message's generation if we
     * have it, otherwise the campaign's product/offer.
     */
    private function offer(Lead $lead): string
    {
        $generation = $lead->messages()->latest('id')->first()?->generation ?? [];

        $parts = array_filter([
            $lead->campaign?->product?->name,
            $generation['value_prop'] ?? null,
            isset($generation['angle']) ? "angle: {$generation['angle']}" : null,
        ]);

        return $parts === [] ? 'current campaign offer' : implode(' — ', $parts);
    }

    /**
     * @return array{ok: bool, contact_id: ?string, noted: bool, skipped: ?string, error: ?string}
     */
    private function fail(?string $error): array
    {
        return ['ok' => false, 'contact_id' => null, 'noted' => false, 'skipped' => null, 'error' => $error];
    }
}
