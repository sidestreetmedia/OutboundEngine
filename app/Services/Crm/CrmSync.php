<?php

namespace App\Services\Crm;

use App\Mail\ContactAddedToHubspot;
use App\Models\Lead;
use App\Models\Reply;
use Illuminate\Support\Facades\Mail;

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
        $context = $this->context($lead);
        $noted = false;

        if ($contactId) {
            $note = $this->hubspot->addNote($contactId, $this->noteBody($lead, $context));
            $noted = $note['ok'];
        }

        $lead->hubspot_contact_id = $contactId;
        $lead->hubspot_synced_at = now();
        $lead->save();

        $this->notify($lead, $contactId, $context);

        return ['ok' => true, 'contact_id' => $contactId, 'noted' => $noted, 'skipped' => null, 'error' => null];
    }

    /**
     * The shared facts about this win — campaign, the CTA/offer they responded
     * to, and their reply — reused for both the HubSpot note and the summary
     * email so the two never drift.
     *
     * @return array{campaign: string, offer: string, reply: ?\App\Models\Reply, reply_snippet: ?string}
     */
    private function context(Lead $lead): array
    {
        $reply = $lead->replies()
            ->where('classification', Reply::CLASS_INTERESTED)
            ->latest('received_at')
            ->first();

        return [
            'campaign' => $lead->campaign?->name ?: 'OutboundEngine',
            'offer' => $this->offer($lead),
            'reply' => $reply,
            'reply_snippet' => ($reply && filled($reply->body)) ? trim(mb_substr((string) $reply->body, 0, 240)) : null,
        ];
    }

    /**
     * @param  array{campaign: string, offer: string, reply: ?\App\Models\Reply, reply_snippet: ?string}  $context
     */
    private function noteBody(Lead $lead, array $context): string
    {
        $lines = [
            'Responded positively via OutboundEngine.',
            "Campaign: {$context['campaign']}",
            'Offer / CTA: ' . $context['offer'],
        ];

        if ($context['reply_snippet']) {
            $lines[] = "Their reply: \"{$context['reply_snippet']}\"";
        }

        $who = trim(($lead->title ?: '') . ($lead->company ? " at {$lead->company}" : ''));
        if ($who !== '') {
            $lines[] = $who;
        }

        return implode("\n", $lines);
    }

    /**
     * Email a summary of the add to the configured address. Best-effort: a mail
     * failure never fails the push, since the contact is already in HubSpot.
     *
     * @param  array{campaign: string, offer: string, reply: ?\App\Models\Reply, reply_snippet: ?string}  $context
     */
    private function notify(Lead $lead, ?string $contactId, array $context): void
    {
        $to = config('outbound.hubspot.notify_email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new ContactAddedToHubspot($lead, $contactId, [
                'campaign' => $context['campaign'],
                'offer' => $context['offer'],
                'reply_snippet' => $context['reply_snippet'],
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
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
