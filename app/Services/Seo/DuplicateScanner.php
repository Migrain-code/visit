<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\SeoSearchQuery;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * Yayınlanmış tekrarları tarar (spec §3.5).
 *
 * Üretim filtresi (modül 4) BUNDAN SONRASINI korur; geçmişteki tekrarları temizlemez.
 *
 * İKİ EŞİK, BİLEREK FARKLI:
 *   tarama  (0.55) → "çakışma olabilir", ekranda listelenir, buton YOK
 *   birleştirme (0.90) → "pratikte aynı yazı", tek tuşla birleştirilebilir
 *
 * %67 benzeyen iki yazıyı otomatik birleştirmek İÇERİK SİLMEKTİR (spec §7.6).
 */
class DuplicateScanner
{
    public function scanThreshold(): float
    {
        return SeoConfig::threshold('duplicate_scan_threshold', (float) config('seo.duplicate.scan_threshold', 0.55));
    }

    public function mergeThreshold(): float
    {
        $merge = SeoConfig::threshold('duplicate_merge_threshold', (float) config('seo.duplicate.merge_threshold', 0.90));

        // Birleştirme eşiği tarama eşiğinin ALTINA düşemez; düşerse içerik silinir.
        return max($merge, $this->scanThreshold());
    }

    /**
     * Çakışan blog çiftleri, benzerliğe göre azalan sırada.
     *
     * @return Collection<int, array{a: Blog, b: Blog, score: float, mergeable: bool, winner_id: int, loser_id: int}>
     */
    public function pairs(): Collection
    {
        $posts = Blog::query()
            ->whereNull('merged_into_id')
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'status', 'publish_at', 'created_at', 'primary_keyword']);

        $metrics = $this->metrics();
        $scanThreshold = $this->scanThreshold();
        $mergeThreshold = $this->mergeThreshold();

        $pairs = collect();

        // O(n²) — blog sayısı büyürse buraya sınır koymak gerekir (spec §7.13).
        foreach ($posts as $i => $a) {
            foreach ($posts->slice($i + 1) as $b) {
                $score = TurkishText::similarity($a->title, $b->title);

                if ($score < $scanThreshold) {
                    continue;
                }

                [$winner, $loser] = $this->rank($a, $b, $metrics);

                $pairs->push([
                    'a' => $a,
                    'b' => $b,
                    'score' => $score,
                    'mergeable' => $score >= $mergeThreshold,
                    'winner_id' => $winner->getKey(),
                    'loser_id' => $loser->getKey(),
                ]);
            }
        }

        return $pairs->sortByDesc('score')->values();
    }

    /**
     * KAZANANI VERİ SEÇER, sen değil (spec §3.5).
     * Sıra: tıklama → gösterim → eski yayın tarihi → KISA SLUG.
     *
     * Son ölçüt önemlidir: çakışan ikinci yazı genelde "-2" ekli slug'la kaydedilir (spec §7.8).
     *
     * @param  array<string, array{clicks: int, impressions: int}>  $metrics
     * @return array{0: Blog, 1: Blog}
     */
    public function rank(Blog $a, Blog $b, array $metrics): array
    {
        $ma = $metrics[PathNormalizer::normalize($a->path())] ?? ['clicks' => 0, 'impressions' => 0];
        $mb = $metrics[PathNormalizer::normalize($b->path())] ?? ['clicks' => 0, 'impressions' => 0];

        if ($ma['clicks'] !== $mb['clicks']) {
            return $ma['clicks'] > $mb['clicks'] ? [$a, $b] : [$b, $a];
        }

        if ($ma['impressions'] !== $mb['impressions']) {
            return $ma['impressions'] > $mb['impressions'] ? [$a, $b] : [$b, $a];
        }

        $da = $a->publish_at ?? $a->created_at;
        $db = $b->publish_at ?? $b->created_at;

        if ($da && $db && ! $da->eq($db)) {
            return $da->lt($db) ? [$a, $b] : [$b, $a];
        }

        if (strlen($a->slug) !== strlen($b->slug)) {
            return strlen($a->slug) < strlen($b->slug) ? [$a, $b] : [$b, $a];
        }

        return $a->getKey() < $b->getKey() ? [$a, $b] : [$b, $a];
    }

    /** @return array<string, array{clicks: int, impressions: int}> */
    public function metrics(): array
    {
        $rows = SeoSearchQuery::query()->pages()->get(['path', 'clicks', 'impressions']);
        $out = [];

        foreach ($rows as $row) {
            $path = PathNormalizer::normalize((string) $row->path);
            $out[$path]['clicks'] = ($out[$path]['clicks'] ?? 0) + (int) $row->clicks;
            $out[$path]['impressions'] = ($out[$path]['impressions'] ?? 0) + (int) $row->impressions;
        }

        return $out;
    }
}
