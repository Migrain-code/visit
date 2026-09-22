<?php

namespace App\Models;

use App\Support\PathNormalizer;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Model;

/**
 * Reddedilen öneri bir daha çıkmasın (spec §3.6).
 */
class InternalLinkSuggestionReject extends Model
{
    protected $fillable = ['blog_post_id', 'target_url', 'anchor_text', 'row_hash'];

    public static function hashFor(int $blogId, string $targetUrl, string $anchor): string
    {
        return md5($blogId.'|'.PathNormalizer::hash($targetUrl).'|'.TurkishText::lower($anchor));
    }

    public static function remember(int $blogId, string $targetUrl, string $anchor): void
    {
        static::updateOrCreate(
            ['row_hash' => static::hashFor($blogId, $targetUrl, $anchor)],
            ['blog_post_id' => $blogId, 'target_url' => $targetUrl, 'anchor_text' => $anchor],
        );
    }
}
