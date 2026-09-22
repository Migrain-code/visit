<?php

namespace App\Models;

use App\Models\Concerns\RecordsHits;
use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NotFoundLog extends Model
{
    use RecordsHits;

    protected $fillable = [
        'path', 'path_hash', 'hits', 'last_referrer', 'last_user_agent',
        'resolved', 'suggested_path', 'suggestion_score', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
            'last_seen_at' => 'datetime',
            'suggestion_score' => 'float',
        ];
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    /** 404'ü kaydeder. İsteği ASLA bozmaz (spec §3.8). */
    public static function record(string $path, ?string $referrer = null, ?string $userAgent = null): ?self
    {
        $normalized = PathNormalizer::normalize($path);

        return static::incrementOrCreate(
            'path_hash',
            PathNormalizer::hash($normalized),
            [
                'path' => Str::limit($normalized, 490, ''),
                'hits' => 1,
                'last_referrer' => $referrer ? Str::limit($referrer, 490, '') : null,
                'last_user_agent' => $userAgent ? Str::limit($userAgent, 290, '') : null,
                'last_seen_at' => now(),
            ],
            [
                'last_referrer' => $referrer ? Str::limit($referrer, 490, '') : null,
                'last_user_agent' => $userAgent ? Str::limit($userAgent, 290, '') : null,
                'last_seen_at' => now(),
            ],
        );
    }
}
