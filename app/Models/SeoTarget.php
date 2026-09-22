<?php

namespace App\Models;

use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelime sahibi olabilecek ticari hedef sayfa (spec §3.1).
 */
class SeoTarget extends Model
{
    public const TYPES = [
        'tour' => 'Tur sayfası',
        'category' => 'Kategori sayfası',
        'province' => 'İl sayfası',
        'district' => 'İlçe sayfası',
        'page' => 'Statik sayfa',
        'home' => 'Ana sayfa',
        'custom' => 'Diğer',
    ];

    protected $fillable = ['name', 'url', 'target_type', 'content_type', 'content_id', 'status', 'note'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $target) {
            $target->url = PathNormalizer::normalize($target->url);
            $target->url_hash = PathNormalizer::hash($target->url);
        });
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class, 'target_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->target_type] ?? $this->target_type;
    }
}
