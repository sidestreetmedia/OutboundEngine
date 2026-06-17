<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostEvent extends Model
{
    use HasFactory;

    public const CATEGORY_LLM = 'llm';
    public const CATEGORY_VERIFICATION = 'verification';
    public const CATEGORY_ENRICHMENT = 'enrichment';
    public const CATEGORY_HOSTING = 'hosting';
    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'campaign_id',
        'costable_type',
        'costable_id',
        'category',
        'provider',
        'description',
        'quantity',
        'unit',
        'amount_usd',
        'billable',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'amount_usd' => 'decimal:4',
            'billable' => 'boolean',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return MorphTo<Model, $this> */
    public function costable(): MorphTo
    {
        return $this->morphTo();
    }
}
