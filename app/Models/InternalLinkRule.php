<?php

namespace App\Models;

use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InternalLinkRule extends Model
{
    public const SCOPES = [
        'all' => 'Tüm içerikler',
        'blog' => 'Blog yazıları',
        'tour' => 'Tur sayfaları',
        'category' => 'Kategori sayfaları',
        'district' => 'İlçe sayfaları',
        'province' => 'İl sayfaları',
        'page' => 'Statik sayfalar',
    ];

    protected $fillable = [
        'anchor_text', 'target_url', 'priority', 'max_per_article',
        'scope_type', 'is_active', 'applied_count',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            $rule->anchor_text = trim((string) $rule->anchor_text);
            $rule->target_url = PathNormalizer::normalize($rule->target_url);
            $rule->target_hash = PathNormalizer::hash($rule->target_url);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->whereIn('scope_type', ['all', $scope]);
    }
}
