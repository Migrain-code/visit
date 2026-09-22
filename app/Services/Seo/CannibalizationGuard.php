<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\SeoKeyword;
use App\Support\SeoConfig;
use App\Support\TurkishText;

/**
 * FİLTRE 2 — kelime çakışması (spec §3.4).
 *
 * TUZAK (spec §7.4): canlıda bu kontrol VARDI ama sonucu kimsenin okumadığı bir
 * "warnings" dizisine yazıyordu. Tespit ediliyor, hiçbir şey olmuyordu.
 * Bu yüzden burada kontrol ya ENGELLER ya da görünür bir yere yazar — uyarı üretip
 * geçmek yoktur.
 */
class CannibalizationGuard
{
    public function maxOwners(): int
    {
        return SeoConfig::int('duplicate_max_owners_per_keyword', (int) config('seo.duplicate.max_owners_per_keyword', 1), 1, 10);
    }

    public function check(?string $keyword, ?int $ignoreBlogId = null): TopicDecision
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return TopicDecision::accept();
        }

        $owners = $this->owners($keyword, $ignoreBlogId);

        if (count($owners) >= $this->maxOwners()) {
            return TopicDecision::reject(
                'cannibalization',
                sprintf('"%s" kelimesini zaten %d içerik hedefliyor (tavan: %d).', $keyword, count($owners), $this->maxOwners()),
                implode(', ', array_slice($owners, 0, 3)),
            );
        }

        return TopicDecision::accept();
    }

    /**
     * Bu kelimeyi hedefleyen içeriklerin etiketleri.
     *
     * @return array<int, string>
     */
    public function owners(string $keyword, ?int $ignoreBlogId = null): array
    {
        $needle = TurkishText::lower(trim($keyword));
        $owners = [];

        if ($needle === '') {
            return [];
        }

        $blogs = Blog::query()
            ->when($ignoreBlogId, fn ($q) => $q->whereKeyNot($ignoreBlogId))
            ->get(['id', 'title', 'primary_keyword']);

        foreach ($blogs as $blog) {
            if (TurkishText::lower((string) $blog->primary_keyword) === $needle) {
                $owners[] = 'Blog: '.$blog->title;
            }
        }

        // Sahibi olan bir kelime kaydı da çakışma sayılır.
        $keywords = SeoKeyword::query()
            ->where('keyword_hash', md5($needle))
            ->whereNotNull('target_id')
            ->with('target:id,name')
            ->get();

        foreach ($keywords as $row) {
            $owners[] = 'Hedef: '.($row->target?->name ?? '#'.$row->target_id);
        }

        return array_values(array_unique($owners));
    }
}
