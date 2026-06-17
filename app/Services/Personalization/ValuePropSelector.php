<?php

namespace App\Services\Personalization;

use App\Models\Lead;
use App\Models\Product;
use App\Models\ValueProp;
use Illuminate\Support\Collection;

/**
 * Chooses which value prop a message should lead with for a given lead.
 *
 * The "one thing" rule: each message is built around a single value prop, not a
 * grab-bag. Value props are scored by how well their persona matches the lead's
 * title/seniority; a multi-step sequence rotates through the ordered list so each
 * step opens with a different, still-relevant angle.
 */
class ValuePropSelector
{
    /**
     * Value props for a lead, best persona-match first.
     *
     * @return Collection<int, ValueProp>
     */
    public function forLead(Lead $lead, Product $product): Collection
    {
        $valueProps = $product->valueProps()->with('persona')->get();

        if ($valueProps->isEmpty()) {
            return collect();
        }

        return $valueProps
            ->sortByDesc(fn (ValueProp $vp) => $this->score($vp, $lead))
            ->values();
    }

    /**
     * The single value prop to lead with for a step index (0-based), rotating
     * so steps don't repeat when several match.
     */
    public function forStep(Lead $lead, Product $product, int $stepIndex): ?ValueProp
    {
        $ordered = $this->forLead($lead, $product);

        if ($ordered->isEmpty()) {
            return null;
        }

        return $ordered[$stepIndex % $ordered->count()];
    }

    private function score(ValueProp $vp, Lead $lead): int
    {
        $persona = $vp->persona;

        // Company-level value props fit anyone — usable, but not a targeted match.
        if (! $persona) {
            return 1;
        }

        $title = mb_strtolower(trim((string) $lead->title));

        if ($title === '') {
            return 2; // persona-targeted, but we can't confirm the fit without a title
        }

        $score = 0;
        $needle = mb_strtolower((string) ($persona->role ?: $persona->name));

        foreach (preg_split('/\s+/', $needle) ?: [] as $token) {
            $token = trim($token);
            if (mb_strlen($token) >= 3 && str_contains($title, $token)) {
                $score += 3;
            }
        }

        $seniority = mb_strtolower((string) $persona->seniority);
        if ($seniority === 'executive'
            && preg_match('/\b(ceo|cfo|coo|cmo|cto|chief|founder|owner|president|vp|partner|director|head)\b/', $title)) {
            $score += 2;
        }

        return $score > 0 ? $score + 3 : 1;
    }
}
