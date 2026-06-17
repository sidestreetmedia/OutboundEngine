<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'one_liner',
        'description',
        'profile',
        'brain_built_at',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'brain_built_at' => 'datetime',
        ];
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** @return HasMany<ProductSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(ProductSource::class);
    }

    /** @return HasMany<Persona, $this> */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    /** @return HasMany<ValueProp, $this> */
    public function valueProps(): HasMany
    {
        return $this->hasMany(ValueProp::class);
    }

    /**
     * Whether the brain builder has produced a structured profile yet.
     */
    public function hasBrain(): bool
    {
        return ! is_null($this->brain_built_at) && ! empty($this->profile);
    }
}
