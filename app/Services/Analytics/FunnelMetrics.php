<?php

namespace App\Services\Analytics;

use App\Models\Campaign;
use App\Models\CostEvent;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Reply;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns the raw tables into the numbers that matter: how many leads made it to
 * each stage of the funnel, what the replies looked like, and what it cost.
 * Replies — specifically positive replies — are the metric everything optimizes
 * for, so they're front and centre.
 */
class FunnelMetrics
{
    public function forCampaign(Campaign $campaign): array
    {
        return [
            'scope' => $campaign->name,
            'funnel' => $this->funnel($campaign->id),
            'messages' => $this->messagesByStatus($campaign->id),
            'replies' => $this->repliesByClass($campaign->id),
            'rates' => $this->rates($campaign->id),
            'cost' => $this->costForCampaign($campaign),
        ];
    }

    public function overall(): array
    {
        return [
            'scope' => 'All campaigns',
            'funnel' => $this->funnel(null),
            'messages' => $this->messagesByStatus(null),
            'replies' => $this->repliesByClass(null),
            'rates' => $this->rates(null),
            'cost' => $this->cost(null),
        ];
    }

    /**
     * @return array{leads:int,verified:int,generated:int,approved:int,pushed:int,replied:int,positive:int,suppressed:int}
     */
    private function funnel(?int $campaignId): array
    {
        $base = fn (): Builder => $this->leads($campaignId);
        $approved = [Message::STATUS_APPROVED, Message::STATUS_QUEUED, Message::STATUS_SENT];

        return [
            'leads' => $base()->count(),
            'verified' => $base()->whereNotNull('verified_at')->count(),
            'generated' => $base()->whereHas('messages')->count(),
            'approved' => $base()->whereHas('messages', fn ($q) => $q->whereIn('status', $approved))->count(),
            'pushed' => $base()->whereNotNull('pushed_at')->count(),
            'replied' => $base()->whereHas('replies')->count(),
            'positive' => $base()->whereHas('replies', fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED))->count(),
            'suppressed' => $base()->where('status', Lead::STATUS_SUPPRESSED)->count(),
        ];
    }

    /**
     * @return array{total:int, by_status:array<string,int>}
     */
    private function messagesByStatus(?int $campaignId): array
    {
        $rows = Message::query()
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return ['total' => (int) $rows->sum(), 'by_status' => $rows->map(fn ($c) => (int) $c)->all()];
    }

    /**
     * @return array{total:int, positive:int, by_class:array<string,int>}
     */
    private function repliesByClass(?int $campaignId): array
    {
        $rows = Reply::query()
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->selectRaw('classification, count(*) as c')
            ->groupBy('classification')
            ->pluck('c', 'classification');

        $byClass = $rows->mapWithKeys(fn ($c, $k) => [(string) ($k ?: 'unclassified') => (int) $c])->all();

        return [
            'total' => (int) $rows->sum(),
            'positive' => $byClass[Reply::CLASS_INTERESTED] ?? 0,
            'by_class' => $byClass,
        ];
    }

    /**
     * @return array{positive_reply_rate:float, reply_rate:float}
     */
    private function rates(?int $campaignId): array
    {
        $pushed = $this->leads($campaignId)->whereNotNull('pushed_at')->count();

        if ($pushed === 0) {
            return ['positive_reply_rate' => 0.0, 'reply_rate' => 0.0];
        }

        $replied = $this->leads($campaignId)->whereHas('replies')->count();
        $positive = $this->leads($campaignId)
            ->whereHas('replies', fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED))
            ->count();

        return [
            'positive_reply_rate' => round($positive / $pushed * 100, 2),
            'reply_rate' => round($replied / $pushed * 100, 2),
        ];
    }

    /**
     * @return array{total_usd:float, by_category:array<string,float>}
     */
    private function cost(?int $campaignId): array
    {
        $rows = CostEvent::query()
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->selectRaw('category, sum(amount_usd) as s')
            ->groupBy('category')
            ->pluck('s', 'category');

        return [
            'total_usd' => round((float) $rows->sum(), 4),
            'by_category' => $rows->map(fn ($s) => round((float) $s, 4))->all(),
        ];
    }

    /**
     * Per-campaign cost, attributed however it was recorded: directly on the
     * event, or via a costable lead or reply belonging to the campaign.
     *
     * @return array{total_usd:float, by_category:array<string,float>}
     */
    private function costForCampaign(Campaign $campaign): array
    {
        $leadIds = $campaign->leads()->pluck('id');
        $replyIds = $campaign->replies()->pluck('id');

        $rows = CostEvent::query()
            ->where(function ($q) use ($campaign, $leadIds, $replyIds) {
                $q->where('campaign_id', $campaign->id)
                    ->orWhere(fn ($q) => $q->where('costable_type', Lead::class)->whereIn('costable_id', $leadIds))
                    ->orWhere(fn ($q) => $q->where('costable_type', Reply::class)->whereIn('costable_id', $replyIds));
            })
            ->selectRaw('category, sum(amount_usd) as s')
            ->groupBy('category')
            ->pluck('s', 'category');

        return [
            'total_usd' => round((float) $rows->sum(), 4),
            'by_category' => $rows->map(fn ($s) => round((float) $s, 4))->all(),
        ];
    }

    private function leads(?int $campaignId): Builder
    {
        return $campaignId
            ? Lead::where('campaign_id', $campaignId)
            : Lead::query();
    }
}
