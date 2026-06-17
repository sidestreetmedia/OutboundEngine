<?php

namespace App\Services\Apollo;

use App\Models\CostEvent;
use App\Services\Cost\CostMeter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Wraps the Apollo API. Two operations, deliberately split by cost:
 *
 *  - searchPeople(): FREE. Apollo charges no credits for search, and it returns
 *    no email addresses. This is how net-new prospects come in.
 *  - enrichPerson(): COSTS 1 credit. Reveals the work email and full profile.
 *    Every call is metered on the CostMeter.
 *
 * The engine never calls these on its own — only the explicit apollo:* commands
 * do, and the paid one is confirmed and capped. Nothing is purchased without the
 * user asking for it.
 */
class ApolloClient
{
    private const BASE = 'https://api.apollo.io/api/v1';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly CostMeter $cost,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Search net-new people. Free; returns no emails (those need enrichment).
     *
     * @param  array<string, mixed>  $filters  person_titles[], person_locations[],
     *         q_organization_keyword_tags[], organization_num_employees_ranges[], q_keywords
     * @return array{ok: bool, people: list<array<string,mixed>>, total: int, error: ?string}
     */
    public function searchPeople(array $filters, int $page = 1, int $perPage = 25): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'people' => [], 'total' => 0, 'error' => 'Apollo API key not set.'];
        }

        try {
            $response = $this->client()->post('/mixed_people/api_search', array_merge(
                $this->clean($filters),
                ['page' => max(1, $page), 'per_page' => min(100, max(1, $perPage))],
            ));

            if ($response->failed()) {
                return ['ok' => false, 'people' => [], 'total' => 0, 'error' => "Apollo {$response->status()}: " . $response->body()];
            }

            $people = $response->json('people') ?? [];

            return [
                'ok' => true,
                'people' => $people,
                'total' => (int) ($response->json('pagination.total_entries') ?? count($people)),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'people' => [], 'total' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enrich one person by Apollo id — reveals the work email and full profile.
     * Costs 1 credit, recorded on the cost meter (attributed via $costContext).
     *
     * @param  array<string, mixed>  $costContext  CostEvent fields: campaign_id, costable_type, costable_id
     * @return array{ok: bool, person: ?array<string,mixed>, error: ?string}
     */
    public function enrichPerson(string $apolloId, array $costContext = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'person' => null, 'error' => 'Apollo API key not set.'];
        }

        try {
            $response = $this->client()->post('/people/match', [
                'id' => $apolloId,
                'reveal_personal_emails' => false,
            ]);

            if ($response->failed()) {
                return ['ok' => false, 'person' => null, 'error' => "Apollo {$response->status()}: " . $response->body()];
            }

            $person = $response->json('person');

            // Only a real match is billable; a 200 with no person isn't charged.
            if ($person !== null) {
                $this->recordCredit($costContext);
            }

            return ['ok' => true, 'person' => $person, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'person' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $costContext
     */
    private function recordCredit(array $costContext): void
    {
        $rate = (float) config('outbound.apollo.cost_per_credit', 0.0);

        $this->cost->record(CostEvent::CATEGORY_ENRICHMENT, $rate, array_merge([
            'provider' => 'apollo',
            'quantity' => 1,
            'unit' => 'credit',
            'description' => 'apollo enrichment',
        ], $costContext));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function clean(array $filters): array
    {
        return array_filter($filters, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl(self::BASE)->timeout(30);
    }
}
