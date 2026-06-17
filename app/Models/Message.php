<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Message extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'lead_id',
        'sequence_step_id',
        'campaign_id',
        'value_prop_id',
        'position',
        'subject',
        'body',
        'status',
        'review_note',
        'reviewed_at',
        'sent_at',
        'generation',
        'meta',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'sent_at' => 'datetime',
            'generation' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<SequenceStep, $this> */
    public function sequenceStep(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<ValueProp, $this> */
    public function valueProp(): BelongsTo
    {
        return $this->belongsTo(ValueProp::class);
    }

    public function approve(?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'review_note' => $note,
            'reviewed_at' => Carbon::now(),
        ])->save();
    }

    public function reject(string $note): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'review_note' => $note,
            'reviewed_at' => Carbon::now(),
        ])->save();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
