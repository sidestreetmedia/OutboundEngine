<?php

namespace App\Services\Cost;

use App\Models\CostEvent;
use Illuminate\Support\Carbon;

/**
 * Records what API calls cost so burn is visible in the dashboard.
 *
 * The engine never spends money on its own — this only *measures*. Every LLM
 * call, verification, and enrichment lookup writes a row here so you can see
 * exactly where the money goes. Nothing is ever purchased without you.
 */
class CostMeter
{
    /**
     * Record an arbitrary cost in USD.
     *
     * @param  array<string, mixed>  $attributes  Any CostEvent fields:
     *         campaign_id, costable_type/costable_id, provider, description,
     *         quantity, unit, billable, meta, occurred_at.
     */
    public function record(string $category, float $amountUsd, array $attributes = []): CostEvent
    {
        return CostEvent::create(array_merge([
            'category' => $category,
            'amount_usd' => $amountUsd,
            'billable' => true,
            'occurred_at' => Carbon::now(),
        ], $attributes));
    }

    /**
     * Record an LLM call, estimating the dollar cost from token counts using
     * the per-million-token prices in config/outbound.php.
     *
     * @param  array<string, mixed>  $attributes  Extra CostEvent fields (campaign_id,
     *         costable, description, billable, meta to merge, ...).
     */
    public function recordLlmTokens(int $inputTokens, int $outputTokens, array $attributes = []): CostEvent
    {
        $prices = config('outbound.cost.llm_price_per_mtok');
        $inputPrice = (float) ($prices['input'] ?? 0.0);
        $outputPrice = (float) ($prices['output'] ?? 0.0);

        $amountUsd = ($inputTokens / 1_000_000) * $inputPrice
            + ($outputTokens / 1_000_000) * $outputPrice;

        $meta = array_merge([
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ], $attributes['meta'] ?? []);

        unset($attributes['meta']);

        return $this->record(CostEvent::CATEGORY_LLM, $amountUsd, array_merge([
            'provider' => 'anthropic',
            'quantity' => $inputTokens + $outputTokens,
            'unit' => 'tokens',
            'meta' => $meta,
        ], $attributes));
    }
}
