<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\District;
use App\Models\Page;
use App\Models\Tour;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * FİLTRE 1 — başlık benzerliği (spec §3.4).
 *
 * Tam eşleşme YETMEZ: canlıda 77 yazılık bir blogda 4 çift kelimesi kelimesine aynı
 * yazı yayınlandı çünkü tek kelime veya tek ek fark filtreyi geçiyordu (spec §7.1).
 * Bu yüzden gövdelemeli Jaccard kullanılır.
 *
 * Eşik sıkı tarafta seçilir: yanlış RET ucuzdur (üretici bir sonraki adaya geçer),
 * kaçan TEKRAR kalıcı SEO hasarıdır.
 */
class DuplicateGuard
{
    /** @var Collection<int, array{title: string, label: string}>|null */
    private ?Collection $existing = null;

    public function threshold(): float
    {
        return SeoConfig::threshold('duplicate_scan_threshold', (float) config('seo.duplicate.scan_threshold', 0.55));
    }

    public function check(string $title, ?int $ignoreBlogId = null): TopicDecision
    {
        $match = $this->bestMatch($title, $ignoreBlogId);

        if ($match && $match['score'] >= $this->threshold()) {
            return TopicDecision::reject(
                'duplicate_title',
                sprintf('Mevcut içerikle %%%d benzer.', round($match['score'] * 100)),
                $match['label'],
                $match['score'],
            );
        }

        return TopicDecision::accept();
    }

    /** @return array{title: string, label: string, score: float}|null */
    public function bestMatch(string $title, ?int $ignoreBlogId = null): ?array
    {
        $best = null;

        foreach ($this->existingTitles() as $row) {
            if ($ignoreBlogId !== null && ($row['blog_id'] ?? null) === $ignoreBlogId) {
                continue;
            }

            $score = TurkishText::similarity($title, $row['title']);

            if ($best === null || $score > $best['score']) {
                $best = ['title' => $row['title'], 'label' => $row['label'], 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * Karşılaştırılacak TÜM başlıklar — kırpma YOK (spec §3.3).
     * Taslaklar da dahildir: aynı konu iki kez kuyruğa girmesin.
     *
     * @return Collection<int, array{title: string, label: string, blog_id: int|null}>
     */
    public function existingTitles(): Collection
    {
        if ($this->existing !== null) {
            return $this->existing;
        }

        $rows = collect();

        foreach (Blog::query()->get(['id', 'title', 'status']) as $blog) {
            $rows->push([
                'title' => (string) $blog->title,
                'label' => 'Blog: '.$blog->title.($blog->status === Blog::STATUS_PUBLISHED ? '' : ' (taslak)'),
                'blog_id' => $blog->getKey(),
            ]);
        }

        foreach (Tour::query()->get(['id', 'title']) as $tour) {
            $rows->push(['title' => (string) $tour->title, 'label' => 'Tur: '.$tour->title, 'blog_id' => null]);
        }

        foreach (Page::query()->get(['id', 'title']) as $page) {
            $rows->push(['title' => (string) $page->title, 'label' => 'Sayfa: '.$page->title, 'blog_id' => null]);
        }

        foreach (District::query()->with('province:id,name')->get(['id', 'name', 'province_id']) as $district) {
            $label = $district->name.' Çıkışlı Turlar';
            $rows->push(['title' => $label, 'label' => 'İlçe sayfası: '.$label, 'blog_id' => null]);
        }

        return $this->existing = $rows->filter(fn ($r) => trim($r['title']) !== '')->values();
    }

    public function forget(): void
    {
        $this->existing = null;
    }
}
