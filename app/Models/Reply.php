<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reply extends Model
{
    use HasFactory;

    public const CLASS_INTERESTED = 'interested';
    public const CLASS_OBJECTION = 'objection';
    public const CLASS_NOT_NOW = 'not_now';
    public const CLASS_OOO = 'ooo';
    public const CLASS_UNSUBSCRIBE = 'unsubscribe';
    public const CLASS_AUTO_REPLY = 'auto_reply';
    public const CLASS_OTHER = 'other';

    protected $fillable = [
        'lead_id',
        'campaign_id',
        'provider',
        'provider_message_id',
        'from_email',
        'subject',
        'body',
        'classification',
        'is_bounce',
        'is_auto_reply',
        'received_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_bounce' => 'boolean',
            'is_auto_reply' => 'boolean',
            'received_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** A reply that signals genuine interest — the metric that matters. */
    public function isPositive(): bool
    {
        return $this->classification === self::CLASS_INTERESTED;
    }
}
