<?php

namespace App\Services\Analytics;

use App\Models\Lead;
use App\Models\Reply;
use Illuminate\Support\Collection;

/**
 * Answers "what's actually working?" by grouping contacted leads along a
 * dimension — which value prop, step angle, job title, or industry — and scoring
 * each group on positive-reply rate. Optimizing on replies, not opens, is the
 * whole point; this is where that optimization gets its signal.
 *
 * A lead can fall into several copy segments (its sequence may use more than one
 * value prop), so it's counted in each distinct segment it actually received.
 */
class SegmentPerformance
{
    /** @return list<array{segment:string,sent:int,positive:int,positive_rate:float}> */
    public function byValueProp(?int $campaignId = null): array
    {
        return $this->analyze(
            $this->contactedLeads($campaignId),
            fn (Lead $lead) => $lead->messages->map(fn ($m) => $m->valueProp?->headline)->all(),
        );
    }

    /** @return list<array{segment:string,sent:int,positive:int,positive_rate:float}> */
    public function byAngle(?int $campaignId = null): array
    {
        return $this->analyze(
            $this->contactedLeads($campaignId),
            fn (Lead $lead) => $lead->messages->map(fn ($m) => $m->generation['angle'] ?? null)->all(),
        );
    }

    /** @return list<array{segment:string,sent:int,positive:int,positive_rate:float}> */
    public function byTitle(?int $campaignId = null): array
    {
        return $this->analyze(
            $this->contactedLeads($campaignId),
            fn (Lead $lead) => [$lead->title],
        );
    }

    /** @return list<array{segment:string,sent:int,positive:int,positive_rate:float}> */
    public function byIndustry(?int $campaignId = null): array
    {
        return $this->analyze(
            $this->contactedLeads($campaignId),
            fn (Lead $lead) => [$lead->industry],
        );
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  callable(Lead): array<int, ?string>  $segmentsOf
     * @return list<array{segment:string,sent:int,positive:int,positive_rate:float}>
     */
    private function analyze(Collection $leads, callable $segmentsOf): array
    {
        $rows = [];

        foreach ($leads as $lead) {
            $isPositive = $lead->replies->contains(
                fn (Reply $r) => $r->classification === Reply::CLASS_INTERESTED,
            );

            $segments = collect($segmentsOf($lead))
                ->map(fn ($s) => is_string($s) ? trim($s) : $s)
                ->filter()
                ->unique();

            foreach ($segments as $label) {
                $rows[$label] ??= ['segment' => $label, 'sent' => 0, 'positive' => 0];
                $rows[$label]['sent']++;
                if ($isPositive) {
                    $rows[$label]['positive']++;
                }
            }
        }

        $out = array_map(function (array $r) {
            $r['positive_rate'] = $r['sent'] > 0 ? round($r['positive'] / $r['sent'] * 100, 2) : 0.0;

            return $r;
        }, array_values($rows));

        usort($out, fn ($a, $b) => [$b['positive_rate'], $b['sent']] <=> [$a['positive_rate'], $a['sent']]);

        return $out;
    }

    /**
     * @return Collection<int, Lead>
     */
    private function contactedLeads(?int $campaignId): Collection
    {
        return Lead::query()
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->whereNotNull('pushed_at')
            ->with(['messages.valueProp', 'replies'])
            ->get();
    }
}
