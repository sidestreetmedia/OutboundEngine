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
    | Apollo — automatic lead source + enrichment.
    |--------------------------------------------------------------------------
    | Search is free (no Apollo credits) and returns no emails. Revealing an
    | email is enrichment, which costs 1 credit per person. The engine never
    | spends on its own — enrichment is an explicit, confirmed, capped command.
    | Set cost_per_credit to your plan's rate so the cost meter shows dollars.
    */
    'apollo' => [
        'key' => env('APOLLO_API_KEY'),
        'cost_per_credit' => (float) env('APOLLO_COST_PER_CREDIT', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | HubSpot — CRM sync for positive replies.
    |--------------------------------------------------------------------------
    | When a contact replies positively to the current CTA, push them into
    | HubSpot as a contact with a note capturing the campaign + offer. Auth is a
    | private-app token; HubSpot has no per-call charge.
    */
    'hubspot' => [
        'key' => env('HUBSPOT_API_KEY'),
        // Email a summary here whenever a contact is added to HubSpot. Blank = off.
        'notify_email' => env('HUBSPOT_NOTIFY_EMAIL'), // default seeded into settings; blank = off
        // Optional: portal id, used to deep-link the contact record in that email.
        'portal_id' => env('HUBSPOT_PORTAL_ID'),
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
