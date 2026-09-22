<?php

namespace App\Models;

use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SUGGESTION = 'auto_suggestion';

    public const SOURCE_DUPLICATE_MERGE = 'duplicate_merge';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Elle eklendi',
        self::SOURCE_SUGGESTION => 'Otomatik öneri',
        self::SOURCE_DUPLICATE_MERGE => 'Çakışma birleştirme',
    ];

    protected $fillable = [
        'from_path', 'to_path', 'status_code', 'is_active',
        'source', 'confidence', 'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
            'confidence' => 'float',
        ];
    }

    /**
     * from_hash'i MODELİN KENDİSİ doldurur. Elle INSERT'te unutulursa yönlendirme
     * sessizce çalışmaz (spec §7.10).
     */
    protected static function booted(): void
    {
        static::saving(function (self $redirect) {
            $redirect->from_path = PathNormalizer::normalize($redirect->from_path);
            $redirect->from_hash = PathNormalizer::hash($redirect->from_path);

            // Hedef site içiyse normalleştir; tam URL ise dokunma.
            if (! preg_match('#^https?://#i', (string) $redirect->to_path)) {
                $redirect->to_path = PathNormalizer::normalize($redirect->to_path);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Yalnız 404 anında çağrılır (spec §10.6). */
    public static function lookup(string $path): ?self
    {
        return static::query()->active()->where('from_hash', PathNormalizer::hash($path))->first();
    }

    public function registerHit(): void
    {
        $this->forceFill([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ])->saveQuietly();
    }

    /** Zincir oluşmasın: hedefin kendisi yönlendiriliyorsa son hedefi bul. */
    public function resolveTarget(int $maxHops = 5): string
    {
        $target = $this->to_path;
        $seen = [$this->from_hash];

        for ($i = 0; $i < $maxHops; $i++) {
            if (preg_match('#^https?://#i', $target)) {
                return $target;
            }

            $next = static::query()->active()->where('from_hash', PathNormalizer::hash($target))->first();

            if (! $next || in_array($next->from_hash, $seen, true)) {
                return $target;
            }

            $seen[] = $next->from_hash;
            $target = $next->to_path;
        }

        return $target;
    }
}
