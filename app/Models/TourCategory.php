<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tur kategorisi: "Günübirlik Turlar", "Kültür Turları", "Yurt Dışı Turları"...
 *
 * Kategori sayfaları tur sayfalarından AYRI bir arama eksenidir: ziyaretçi
 * "günübirlik turlar" diye arar, belirli bir turun adını değil.
 */
class TourCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'image', 'description', 'content', 'faqs',
        'meta_title', 'meta_description', 'is_active', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'faqs' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function activeTours(): HasMany
    {
        return $this->tours()->where('is_active', true)->orderBy('sort_order')->orderBy('title');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function path(): string
    {
        return '/turlar/'.$this->slug;
    }

    public function getUrlAttribute(): string
    {
        return url($this->path());
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? media_url($this->image) : null;
    }

    public function getIconClassAttribute(): string
    {
        return $this->icon ?: 'fa-solid fa-map-location-dot';
    }
}
