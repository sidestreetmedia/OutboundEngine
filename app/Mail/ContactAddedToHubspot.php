<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent whenever a positive-reply lead is pushed into HubSpot — a plain-language
 * summary of who they are, the campaign + CTA they responded to, their reply,
 * and the HubSpot contact it created.
 */
class ContactAddedToHubspot extends Mailable
{
    use SerializesModels;

    /**
     * @param  array{campaign: string, offer: string, reply_snippet: ?string}  $summary
     */
    public function __construct(
        public Lead $lead,
        public ?string $contactId,
        public array $summary,
    ) {
    }

    public function envelope(): Envelope
    {
        $campaign = $this->summary['campaign'] ?? 'OutboundEngine';

        return new Envelope(
            subject: "Added to HubSpot: {$this->displayName()} — {$campaign}",
        );
    }

    public function content(): Content
    {
        $portal = config('outbound.hubspot.portal_id');
        $link = ($portal && $this->contactId)
            ? "https://app.hubspot.com/contacts/{$portal}/record/0-1/{$this->contactId}"
            : null;

        return new Content(
            view: 'emails.contact-added',
            with: [
                'lead' => $this->lead,
                'name' => $this->displayName(),
                'contactId' => $this->contactId,
                'link' => $link,
                'summary' => $this->summary,
            ],
        );
    }

    private function displayName(): string
    {
        $name = trim(($this->lead->first_name ?? '') . ' ' . ($this->lead->last_name ?? ''));

        return $name !== '' ? $name : (string) $this->lead->email;
    }
}
