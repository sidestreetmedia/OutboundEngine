<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    use HasFactory;

    public const TYPE_EMAIL = 'email';
    public const TYPE_DOMAIN = 'domain';

    public const REASON_UNSUBSCRIBE = 'unsubscribe';
    public const REASON_BOUNCE = 'bounce';
    public const REASON_COMPLAINT = 'complaint';
    public const REASON_MANUAL = 'manual';

    protected $fillable = [
        'value',
        'type',
        'reason',
        'suppressed_at',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
        ];
    }
}
