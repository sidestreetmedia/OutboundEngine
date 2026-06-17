<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadImport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'original_name',
        'path',
        'status',
        'mapping',
        'total_rows',
        'imported_count',
        'duplicate_count',
        'invalid_count',
        'failed_count',
        'error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
