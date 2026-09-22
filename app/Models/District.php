<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    protected $fillable = [
        'province_id', 'name', 'slug', 'description', 'content', 'pickup_points', 'faqs',
        'meta_title', 'meta_description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pickup_points' => 'array',
            'faqs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getUrlAttribute(): string
    {
        return url('/'.$this->province->slug.'/'.$this->slug);
    }

    public function getFullNameAttribute(): string
    {
        return $this->name.', '.$this->province->name;
    }
}
