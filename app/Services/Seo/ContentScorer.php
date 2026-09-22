<?php

namespace App\Services\Seo;

use App\Models\SeoAnalysis;
use App\Support\SeoIssue;
use App\Support\TurkishText;

/**
 * Deterministik SEO skoru (spec §3.2).
 *
 * 100 puan = meta 30 + içerik 40 + teknik 15 + link 15.
 * AI ÇAĞIRMAZ: ücretsiz, tekrarlanabilir, cron'da güvenli, test edilebilir (spec §1).
 */
class ContentScorer
{
    /** @return array{score: int, scores: array<string,int>, issues: array<int,string>} */
    public function score(ScorableContent $content): array
    {
        $issues = [];

        $meta = $this->scoreMeta($content, $issues);
        $body = $this->scoreContent($content, $issues);
        $technical = $this->scoreTechnical($content, $issues);
        $links = $this->scoreLinks($content, $issues);

        $scores = [
            'meta' => $meta,
            'content' => $body,
            'technical' => $technical,
            'links' => $links,
        ];

        return [
            'score' => (int) min(100, array_sum($scores)),
            'scores' => $scores,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /** Skoru kaydeder; indeks durumu KORUNUR (ayrı görev doldurur). */
    public function persist(ScorableContent $content): SeoAnalysis
    {
        $result = $this->score($content);

        $analysis = SeoAnalysis::firstOrNew([
            'content_type' => $content->contentType,
            'content_id' => $content->contentId,
        ]);

        $analysis->fill([
            'url' => $content->url,
            'score' => $result['score'],
            'scores' => $result['scores'],
            'issues' => $result['issues'],
            'analyzed_at' => now(),
        ])->save();

        return $analysis;
    }

    /** meta = 30 */
    private function scoreMeta(ScorableContent $content, array &$issues): int
    {
        $points = 0;
        $max = (int) config('seo.score.meta_title_max', 60);
        $descMax = (int) config('seo.score.meta_description_max', 155);
        $descMin = (int) config('seo.score.meta_description_min', 70);

        $title = trim((string) ($content->metaTitle ?: $content->title));
        $titleLength = mb_strlen($title);

        if ($titleLength === 0) {
            $issues[] = SeoIssue::META_TITLE_MISSING;
        } else {
            $points += 8;

            if ($titleLength <= $max) {
                $points += 6;
            } else {
                $issues[] = SeoIssue::META_TITLE_TOO_LONG;
            }

            if ($titleLength >= 25) {
                $points += 3;
            } else {
                $issues[] = SeoIssue::META_TITLE_TOO_SHORT;
            }
        }

        $description = trim((string) $content->metaDescription);
        $descLength = mb_strlen($description);

        if ($descLength === 0) {
            $issues[] = SeoIssue::META_DESC_MISSING;
        } else {
            $points += 8;

            if ($descLength >= $descMin && $descLength <= $descMax) {
                $points += 5;
            } else {
                $issues[] = SeoIssue::META_DESC_LENGTH;
            }
        }

        return $points;
    }

    /** içerik = 40 */
    private function scoreContent(ScorableContent $content, array &$issues): int
    {
        $points = 0;

        // Yapısal kontroller render edilmiş sayfa üzerinden (şablonun ürettiği H1/H2 dahil).
        $structural = $content->structuralHtml();

        // Kelime sayısı YÖNETİCİNİN yazdığı içerik üzerinden: menü ve etiketler şişirmesin.
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content->fullText())) ?? '');
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        $h1Count = preg_match_all('/<h1[\s>]/i', $structural);

        if ($content->isRendered()) {
            // Render edilmiş sayfada TAM BİR H1 beklenir.
            if ($h1Count === 1) {
                $points += 8;
            } elseif ($h1Count === 0) {
                $issues[] = SeoIssue::H1_MISSING;
            } else {
                $issues[] = SeoIssue::H1_MULTIPLE;
            }
        } else {
            // Render edilemedi: gövdede H1 olmamalı, başlık alanı dolu olmalı.
            if ($h1Count > 0) {
                $issues[] = SeoIssue::H1_MULTIPLE;
            } elseif (trim($content->title) === '') {
                $issues[] = SeoIssue::H1_MISSING;
            } else {
                $points += 8;
            }
        }

        $min = (int) config('seo.score.min_words', 300);
        $good = (int) config('seo.score.good_words', 600);

        if ($words >= $min) {
            $points += 12;
        } else {
            $issues[] = SeoIssue::CONTENT_TOO_SHORT;
        }

        if ($words >= $good) {
            $points += 6;
        } elseif ($words >= $min) {
            $issues[] = SeoIssue::CONTENT_THIN;
        }

        if (preg_match('/<h[23][\s>]/i', $structural)) {
            $points += 6;
        } else {
            $issues[] = SeoIssue::H2_MISSING;
        }

        $imageCount = preg_match_all('/<img\b[^>]*>/i', $structural, $images);
        $withoutAlt = 0;

        foreach ($images[0] ?? [] as $tag) {
            if (! preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $tag)) {
                $withoutAlt++;
            }
        }

        if ($imageCount === 0 || $withoutAlt === 0) {
            $points += 5;
        } else {
            $issues[] = SeoIssue::IMAGE_ALT_MISSING;
        }

        if ($content->faqCount > 0) {
            $points += 3;
        } else {
            $issues[] = SeoIssue::FAQ_MISSING;
        }

        return $points;
    }

    /** teknik = 15 */
    private function scoreTechnical(ScorableContent $content, array &$issues): int
    {
        $points = 0;
        $slug = (string) $content->slug;

        if ($slug !== '' && $slug === TurkishText::slug($slug)) {
            $points += 5;
        } else {
            $issues[] = SeoIssue::SLUG_NOT_ASCII;
        }

        if ($content->published) {
            $points += 5;
        } else {
            $issues[] = SeoIssue::NOT_PUBLISHED;
        }

        if ($content->hasImage) {
            $points += 5;
        } else {
            $issues[] = SeoIssue::IMAGE_MISSING;
        }

        return $points;
    }

    /**
     * link = 15
     *
     * Ölçüm YÖNETİCİNİN yazdığı gövde üzerinden yapılır, render edilmiş sayfa üzerinden
     * değil: menü ve altbilgi her sayfada onlarca link içerir ve kontrolü anlamsızlaştırır.
     * Burada aranan, metin içine gömülü BAĞLAMSAL iç linklerdir — iç link motorunun
     * (modül 6) üzerinde çalıştığı şey.
     */
    private function scoreLinks(ScorableContent $content, array &$issues): int
    {
        $points = 0;
        $html = $content->fullText();

        preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $matches);
        $hrefs = $matches[1] ?? [];

        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $internal = 0;
        $empty = 0;

        foreach ($hrefs as $href) {
            $href = trim($href);

            if ($href === '' || $href === '#') {
                $empty++;

                continue;
            }

            if (str_starts_with($href, '/')) {
                $internal++;

                continue;
            }

            if ($host && str_contains($href, (string) $host)) {
                $internal++;
            }
        }

        if ($internal >= 1) {
            $points += 8;
        } else {
            $issues[] = SeoIssue::INTERNAL_LINK_MISSING;
        }

        if ($internal >= 2) {
            $points += 4;
        } elseif ($internal === 1) {
            $issues[] = SeoIssue::INTERNAL_LINK_FEW;
        }

        if ($empty === 0) {
            $points += 3;
        } else {
            $issues[] = SeoIssue::EMPTY_LINK;
        }

        return $points;
    }
}
