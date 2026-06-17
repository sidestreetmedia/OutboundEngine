<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductSource extends Model
{
    use HasFactory;

    public const TYPE_UPLOAD = 'upload';
    public const TYPE_URL = 'url';

    public const STATUS_PENDING = 'pending';
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'product_id',
        'type',
        'label',
        'original_name',
        'mime',
        'path',
        'bytes',
        'url',
        'status',
        'extracted_text',
        'char_count',
        'error',
        'extracted_at',
        'meta',
    ];

    /**
     * Mirror the DB default so a freshly made model reports "pending" in memory,
     * not null, before it's persisted or refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'extracted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Store extracted text and flip the source to "extracted".
     */
    public function markExtracted(string $text): void
    {
        $this->forceFill([
            'status' => self::STATUS_EXTRACTED,
            'extracted_text' => $text,
            'char_count' => mb_strlen($text),
            'error' => null,
            'extracted_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Record an extraction failure with its reason.
     */
    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => $reason,
            'extracted_at' => Carbon::now(),
        ])->save();
    }
}
