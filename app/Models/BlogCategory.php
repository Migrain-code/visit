<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'meta_title', 'meta_description',
        'is_active', 'auto_generate', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'auto_generate' => 'boolean'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->where('status', Blog::STATUS_PUBLISHED);
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
        return url('/blog/kategori/'.$this->slug);
    }
}
