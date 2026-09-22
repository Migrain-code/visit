<?php

namespace App\Services\InternalLink;

use App\Models\Blog;
use App\Models\InternalLinkRule;
use App\Models\InternalLinkSuggestionReject;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * İç link öneri motoru — DETERMİNİSTİK, AI kullanmaz (spec §1, §3.6).
 *
 * Motor kelime UYDURMAZ: aday anchor metinleri yalnız gerçek hedef havuzundan gelir
 * (hedef sayfa adları ve o hedefe atanmış anahtar kelimeler).
 */
class SuggestionEngine
{
    public function __construct(protected LinkApplier $applier) {}

    public function minScore(): int
    {
        return SeoConfig::int('internal_links_min_score', (int) config('seo.internal_links.min_score', 60), 0, 100);
    }

    /**
     * Bir yazı için öneriler.
     *
     * @return Collection<int, array{blog_id:int, anchor:string, target_url:string, target_name:string, score:int, occurrences:int}>
     */
    public function forBlog(Blog $blog): Collection
    {
        $body = (string) $blog->body_html;

        if (trim($body) === '') {
            return collect();
        }

        // Dolu makaleye öneri ÇIKMAZ (spec §3.6).
        $remaining = $this->remainingSlots($blog);

        if ($remaining <= 0) {
            return collect();
        }

        $plain = TurkishText::lower(strip_tags($body));
        $selfHash = PathNormalizer::hash($blog->path());

        $rejected = InternalLinkSuggestionReject::query()
            ->where('blog_post_id', $blog->getKey())
            ->pluck('row_hash')
            ->flip();

        $existingRules = InternalLinkRule::query()->pluck('target_hash', 'anchor_text');

        $suggestions = collect();

        foreach ($this->candidates() as $candidate) {
            $anchor = $candidate['anchor'];
            $targetHash = PathNormalizer::hash($candidate['url']);

            if ($targetHash === $selfHash) {
                continue;
            }

            // Zaten kural varsa öneri üretme.
            if (($existingRules[$anchor] ?? null) === $targetHash) {
                continue;
            }

            $hash = InternalLinkSuggestionReject::hashFor($blog->getKey(), $candidate['url'], $anchor);

            if ($rejected->has($hash)) {
                continue;
            }

            $needle = TurkishText::lower($anchor);
            $occurrences = substr_count($plain, $needle);

            if ($occurrences === 0) {
                continue;
            }

            $score = $this->score($candidate, $anchor, $occurrences, $plain);

            if ($score < $this->minScore()) {
                continue;
            }

            $suggestions->push([
                'blog_id' => $blog->getKey(),
                'blog_title' => $blog->title,
                'anchor' => $anchor,
                'target_url' => $candidate['url'],
                'target_name' => $candidate['name'],
                'score' => $score,
                'occurrences' => $occurrences,
            ]);
        }

        return $suggestions->sortByDesc('score')->take($remaining)->values();
    }

    /** @return Collection<int, array{blog_id:int, anchor:string, target_url:string, score:int}> */
    public function all(?int $limit = null): Collection
    {
        $blogs = Blog::query()->published()->orderByDesc('publish_at')->get();

        $out = collect();

        foreach ($blogs as $blog) {
            $out = $out->merge($this->forBlog($blog));

            if ($limit && $out->count() >= $limit) {
                break;
            }
        }

        return $limit ? $out->take($limit)->values() : $out->values();
    }

    public function remainingSlots(Blog $blog): int
    {
        $existing = preg_match_all('/<a\b[^>]*href\s*=\s*["\'](?:\/|'.preg_quote((string) config('app.url'), '/').')/i', (string) $blog->body_html);

        return max(0, $this->applier->maxPerArticle() - $existing);
    }

    /**
     * Gerçek hedef havuzu — UYDURMA YOK (spec §3.6).
     *
     * @return Collection<int, array{anchor: string, url: string, name: string, type: string}>
     */
    public function candidates(): Collection
    {
        $targets = SeoTarget::query()->active()->whereIn('target_type', ['tour', 'category', 'district', 'province', 'page'])->get();
        $keywords = SeoKeyword::query()->active()->whereNotNull('target_id')->get()->groupBy('target_id');

        $out = collect();

        foreach ($targets as $target) {
            // 1) Hedefin kendi adı (ilçe adlarında "/ Rize" ekini at)
            $name = trim(explode('/', $target->name)[0]);

            if (mb_strlen($name) >= 4) {
                $out->push(['anchor' => $name, 'url' => $target->url, 'name' => $target->name, 'type' => $target->target_type]);
            }

            // 2) Bu hedefe ATANMIŞ anahtar kelimeler
            foreach ($keywords->get($target->getKey(), collect()) as $keyword) {
                if (mb_strlen($keyword->keyword) >= 4) {
                    $out->push(['anchor' => $keyword->keyword, 'url' => $target->url, 'name' => $target->name, 'type' => $target->target_type]);
                }
            }
        }

        // Uzun anchor önce denensin.
        return $out->unique(fn ($c) => TurkishText::lower($c['anchor']).'|'.$c['url'])
            ->sortByDesc(fn ($c) => mb_strlen($c['anchor']))
            ->values();
    }

    /** 0-100 deterministik skor. */
    private function score(array $candidate, string $anchor, int $occurrences, string $plain): int
    {
        $score = 40;

        // Uzun ve belirgin anchor daha iyi.
        $words = count(preg_split('/\s+/u', trim($anchor)) ?: []);
        $score += min(25, $words * 10);

        // Ticari hedef (tur sayfası) önceliklidir.
        $score += match ($candidate['type']) {
            'tour' => 20,
            'category' => 16,
            'district', 'province' => 12,
            default => 4,
        };

        // Metinde birden fazla geçmesi doğal bağlam işaretidir.
        $score += min(10, ($occurrences - 1) * 5);

        // Yazının ilk yarısında geçiyorsa daha değerli.
        $position = mb_strpos($plain, TurkishText::lower($anchor));

        if ($position !== false && $position < mb_strlen($plain) / 2) {
            $score += 5;
        }

        return (int) min(100, $score);
    }
}
