<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'role',
        'seniority',
        'okrs',
        'pains',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'okrs' => 'array',
            'pains' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ValueProp, $this> */
    public function valueProps(): HasMany
    {
        return $this->hasMany(ValueProp::class);
    }
}
