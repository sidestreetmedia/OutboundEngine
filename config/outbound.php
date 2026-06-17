<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending platform (Phase 6)
    |--------------------------------------------------------------------------
    | Instantly and Lemlist both implement one contract; this picks the default.
    | OutboundEngine never sends mail itself — it hands sequences to whichever
    | platform is active and reads results back.
    */
    'provider' => env('OE_OUTBOUND_PROVIDER', 'instantly'),

    'providers' => [
        'instantly' => [
            'key' => env('INSTANTLY_API_KEY'),
        ],
        'lemlist' => [
            'key' => env('LEMLIST_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM (Phase 2 — Product Brain)
    |--------------------------------------------------------------------------
    | Reads your decks/site and writes personalized copy.
    */
    'llm' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('OE_LLM_MODEL', 'claude-sonnet-4-6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email verification (Phase 3)
    |--------------------------------------------------------------------------
    | Every lead is verified before it is ever sent to — bounces wreck sender
    | reputation. Provider-agnostic; a real provider is wired in Phase 3.
    */
    'verification' => [
        'provider' => env('OE_VERIFY_PROVIDER'),
        'key' => env('OE_VERIFY_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apollo (later) — automatic lead source. CSV is the v1 source.
    |--------------------------------------------------------------------------
    */
    'apollo' => [
        'key' => env('APOLLO_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost meter
    |--------------------------------------------------------------------------
    | The engine never spends money on its own. It records what API calls cost
    | so burn is visible — nothing is purchased without you. LLM costs are
    | estimated from token counts using the per-million-token prices below;
    | override them via env as pricing changes.
    */
    'cost' => [
        'currency' => 'USD',
        'llm_price_per_mtok' => [
            'input' => (float) env('OE_LLM_PRICE_INPUT_PER_MTOK', 3.0),
            'output' => (float) env('OE_LLM_PRICE_OUTPUT_PER_MTOK', 15.0),
        ],
    ],

];
