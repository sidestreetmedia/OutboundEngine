<?php

namespace App\Services\Crm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper over the HubSpot CRM API for the one thing OutboundEngine needs:
 * dropping a contact who replied positively into the CRM, with a note capturing
 * which campaign and CTA they responded to. Auth is a private-app token
 * (Bearer); HubSpot doesn't charge per call, so nothing here touches the cost
 * meter. Upsert-by-email means re-pushing the same person updates rather than
 * duplicates.
 */
class HubspotClient
{
    private const BASE = 'https://api.hubapi.com';

    // HubSpot-defined association type: Note -> Contact.
    private const NOTE_TO_CONTACT = 202;

    public function __construct(private readonly ?string $token)
    {
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Create or update a contact, keyed on email (single-call upsert).
     *
     * @param  array<string, mixed>  $properties  HubSpot contact properties; email required
     * @return array{ok: bool, id: ?string, error: ?string}
     */
    public function upsertContact(array $properties): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'id' => null, 'error' => 'HubSpot token not set.'];
        }

        $email = $properties['email'] ?? null;

        if (! $email) {
            return ['ok' => false, 'id' => null, 'error' => 'Contact email is required.'];
        }

        try {
            $response = $this->client()->post('/crm/v3/objects/contacts/batch/upsert', [
                'inputs' => [[
                    'idProperty' => 'email',
                    'id' => $email,
                    'properties' => $properties,
                ]],
            ]);

            if ($response->failed()) {
                return ['ok' => false, 'id' => null, 'error' => "HubSpot {$response->status()}: " . $response->body()];
            }

            return ['ok' => true, 'id' => $response->json('results.0.id'), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Attach a note to a contact — the campaign + CTA + reply context.
     *
     * @return array{ok: bool, id: ?string, error: ?string}
     */
    public function addNote(string $contactId, string $body): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'id' => null, 'error' => 'HubSpot token not set.'];
        }

        try {
            $response = $this->client()->post('/crm/v3/objects/notes', [
                'properties' => [
                    'hs_note_body' => $body,
                    'hs_timestamp' => now()->valueOf(),
                ],
                'associations' => [[
                    'to' => ['id' => $contactId],
                    'types' => [[
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => self::NOTE_TO_CONTACT,
                    ]],
                ]],
            ]);

            if ($response->failed()) {
                return ['ok' => false, 'id' => null, 'error' => "HubSpot {$response->status()}: " . $response->body()];
            }

            return ['ok' => true, 'id' => $response->json('id'), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        }
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->baseUrl(self::BASE)
            ->acceptJson()
            ->timeout(30);
    }
}
