<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'content', 'faqs',
        'meta_title', 'meta_description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'faqs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeDistricts(): HasMany
    {
        return $this->districts()->where('is_active', true);
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
        return url('/'.$this->slug);
    }
}
