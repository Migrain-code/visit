<?php

namespace App\Models;

use App\Models\Concerns\RecordsHits;
use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI ajanlarının ziyaret izi (spec §3.9). Hangi botun neyi okuduğunu gösterir.
 */
class AiCrawlerVisit extends Model
{
    use RecordsHits;

    protected $fillable = ['bot', 'path', 'key_hash', 'hits', 'last_status', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /** User-Agent içinde tanınan bir AI botu var mı? */
    public static function detect(?string $userAgent): ?string
    {
        $userAgent = (string) $userAgent;

        if ($userAgent === '') {
            return null;
        }

        foreach ((array) config('seo.ai_bots', []) as $label => $needle) {
            if (stripos($userAgent, (string) $needle) !== false) {
                return (string) $label;
            }
        }

        return null;
    }

    public static function record(string $bot, string $path, ?int $status = null): ?self
    {
        $normalized = PathNormalizer::normalize($path);

        return static::incrementOrCreate(
            'key_hash',
            md5($bot.'|'.$normalized),
            [
                'bot' => $bot,
                'path' => Str::limit($normalized, 490, ''),
                'hits' => 1,
                'last_status' => $status,
                'last_seen_at' => now(),
            ],
            ['last_status' => $status, 'last_seen_at' => now()],
        );
    }
}
