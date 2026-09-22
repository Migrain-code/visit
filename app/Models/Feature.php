<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    public const TYPE_TRUST = 'trust';

    public const TYPE_WHY_US = 'why_us';

    public const TYPE_PROCESS = 'process';

    public const TYPES = [
        self::TYPE_TRUST => 'Güven Unsuru (Hero altı)',
        self::TYPE_WHY_US => 'Neden Biz?',
        self::TYPE_PROCESS => 'Nasıl Çalışıyoruz? (Süreç adımı)',
    ];

    protected $fillable = ['type', 'icon', 'title', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getIconClassAttribute(): string
    {
        return $this->icon ?: 'fa-solid fa-circle-check';
    }
}
