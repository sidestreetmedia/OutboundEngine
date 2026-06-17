<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_RISKY = 'risky';
    public const STATUS_SUPPRESSED = 'suppressed';

    public const VERIFICATION_VALID = 'valid';
    public const VERIFICATION_INVALID = 'invalid';
    public const VERIFICATION_RISKY = 'risky';
    public const VERIFICATION_UNKNOWN = 'unknown';

    protected $fillable = [
        'email',
        'email_normalized',
        'first_name',
        'last_name',
        'title',
        'company',
        'company_domain',
        'industry',
        'location',
        'linkedin_url',
        'status',
        'verification_status',
        'verified_at',
        'source',
        'provider_lead_id',
        'pushed_at',
        'public_token',
        'apollo_id',
        'hubspot_contact_id',
        'hubspot_synced_at',
        'lead_import_id',
        'campaign_id',
        'enrichment',
        'triggers',
        'meta',
    ];

    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'pushed_at' => 'datetime',
            'enrichment' => 'array',
            'triggers' => 'array',
            'hubspot_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<LeadImport, $this> */
    public function leadImport(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('position');
    }

    /** @return HasMany<Reply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    /** @return HasOne<Audit, $this> */
    public function audit(): HasOne
    {
        return $this->hasOne(Audit::class)->latestOfMany();
    }

    /**
     * Record a verification result and move the lead's status accordingly.
     */
    public function applyVerification(string $result): void
    {
        $status = match ($result) {
            self::VERIFICATION_VALID => self::STATUS_VERIFIED,
            self::VERIFICATION_INVALID => self::STATUS_INVALID,
            self::VERIFICATION_RISKY => self::STATUS_RISKY,
            default => $this->status,
        };

        $this->forceFill([
            'verification_status' => $result,
            'verified_at' => Carbon::now(),
            'status' => $status,
        ])->save();
    }

    /**
     * Only verified leads are eligible to be contacted — bounces wreck the
     * sending domains, so unverified and risky addresses never go out.
     */
    public function isSendable(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
