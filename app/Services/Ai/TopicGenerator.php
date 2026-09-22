<?php

namespace App\Services\Ai;

use App\Models\BlogCategory;
use App\Models\SeoKeyword;
use App\Services\Seo\CannibalizationGuard;
use App\Services\Seo\DuplicateGuard;
use App\Services\Seo\KeywordPool;
use App\Services\Seo\TopicDecision;
use App\Support\AutomationLog;
use App\Support\BusinessContext;
use App\Support\SeoConfig;
use App\Support\TurkishText;

/**
 * AI konu üretimi + İKİ DETERMİNİSTİK FİLTRE (spec §3.3, §3.4).
 *
 * Filtreler üretimle BİRLİKTE çalışır, sonraya bırakılmaz (spec §8.7).
 */
class TopicGenerator
{
    public function __construct(
        protected AiClient $ai,
        protected KeywordPool $pool,
        protected DuplicateGuard $duplicateGuard,
        protected CannibalizationGuard $cannibalGuard,
        protected ContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array{
     *   accepted: array<int, array<string, mixed>>,
     *   rejected: array<int, array<string, mixed>>,
     *   pool_size: int,
     *   raw_count: int
     * }
     */
    public function generate(BlogCategory $category, int $wanted = 1): array
    {
        $candidates = max($wanted, SeoConfig::int('blog_topic_candidates', (int) config('seo.blog.topic_candidates', 5), 1, 20));
        $freeKeywords = $this->pool->available(60);

        $payload = $this->ai->json(
            'blog.topics',
            $this->systemPrompt(),
            $this->userPrompt($category, $candidates, $freeKeywords->pluck('keyword')->all()),
            ['content_type' => 'blog_category', 'content_id' => $category->getKey(), 'batch_key' => 'topics.'.now()->format('YmdHis')],
        );

        $topics = $payload['topics'] ?? [];
        $accepted = [];
        $rejected = [];

        foreach ($topics as $topic) {
            if (! is_array($topic)) {
                continue;
            }

            $title = $this->sanitizer->title($topic['title'] ?? null);
            $keyword = $this->sanitizer->title($topic['primary_keyword'] ?? null, 120);

            if ($title === '') {
                $rejected[] = TopicDecision::reject('empty_title', 'Başlık boş geldi.')->toLog('(boş)');

                continue;
            }

            // FİLTRE 1 — başlık benzerliği
            $decision = $this->duplicateGuard->check($title);

            if (! $decision->accepted) {
                $rejected[] = $decision->toLog($title);

                continue;
            }

            // FİLTRE 2 — kelime çakışması
            $decision = $this->cannibalGuard->check($keyword);

            if (! $decision->accepted) {
                $rejected[] = $decision->toLog($title);

                continue;
            }

            // Aynı turda iki kez aynı konuyu kabul etme.
            foreach ($accepted as $already) {
                if (TurkishText::similarity($title, $already['title']) >= $this->duplicateGuard->threshold()) {
                    $rejected[] = TopicDecision::reject('duplicate_in_batch', 'Aynı turdaki başka bir adayla çakışıyor.', $already['title'])->toLog($title);

                    continue 2;
                }
            }

            $accepted[] = [
                'title' => $title,
                'slug' => $this->sanitizer->slug($topic['slug'] ?? null, $title),
                'primary_keyword' => $keyword,
                'search_intent' => $this->intent($topic['search_intent'] ?? null),
                'outline' => array_values(array_filter(array_map(
                    fn ($item) => $this->sanitizer->title(is_array($item) ? ($item['title'] ?? '') : $item, 150),
                    (array) ($topic['outline'] ?? []),
                ))),
            ];

            if (count($accepted) >= $wanted) {
                break;
            }
        }

        // Reddedilen HER aday gerekçesiyle log'lanır (spec §3.4).
        AutomationLog::summary('blog.topics', [
            'category' => $category->name,
            'wanted' => $wanted,
            'raw' => count($topics),
            'accepted' => count($accepted),
            'rejected' => count($rejected),
            'pool_free' => $freeKeywords->count(),
            'rejections' => $rejected,
        ], reasons: $accepted === [] ? ['no_candidates'] : [], changed: $accepted !== []);

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'pool_size' => $freeKeywords->count(),
            'raw_count' => count($topics),
        ];
    }

    private function intent(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return array_key_exists($value, SeoKeyword::INTENTS) ? $value : 'informational';
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Sen '.BusinessContext::describe().' olan bir firmanın',
            'SEO içerik stratejistisin. Türkçe yazarsın.',
            '',
            'Görevin: verilen kategori için BENZERSİZ blog konuları üretmek.',
            '',
            'ÇIKTI KURALI: yalnızca geçerli JSON döndür. Markdown, kod bloğu veya açıklama ekleme.',
            'Şema: {"topics":[{"title":"","slug":"","primary_keyword":"","search_intent":"informational|commercial|transactional|navigational","outline":["",""]}]}',
        ]);
    }

    /** @param array<int, string> $freeKeywords */
    private function userPrompt(BlogCategory $category, int $count, array $freeKeywords): string
    {
        $existing = $this->duplicateGuard->existingTitles()->pluck('title');

        $lines = [];
        $lines[] = 'KATEGORİ: '.$category->name;

        if (filled($category->description)) {
            $lines[] = 'Kategori açıklaması: '.$category->description;
        }

        $lines[] = 'İSTENEN KONU SAYISI: '.$count.' (filtreye takılırsa yedek kalsın diye fazla iste)';
        $lines[] = '';

        // Mevcut TÜM başlıklar — kırpma YOK (spec §3.3).
        $lines[] = 'SİTEDE HÂLİHAZIRDA BULUNAN TÜM BAŞLIKLAR ('.$existing->count().' adet).';
        $lines[] = 'Bunlarla aynı VEYA benzer konu üretme:';

        foreach ($existing as $title) {
            $lines[] = '- '.$title;
        }

        $lines[] = '';

        if ($freeKeywords !== []) {
            // YALNIZ sahiplenilmemiş kelimeler verilir (spec §7.3).
            $lines[] = 'KULLANILABİLİR ANAHTAR KELİME HAVUZU (hiçbiri henüz bir içeriğe atanmamıştır).';
            $lines[] = 'primary_keyword alanını MUTLAKA bu listeden seç:';

            foreach ($freeKeywords as $keyword) {
                $lines[] = '- '.$keyword;
            }
        } else {
            $lines[] = 'ANAHTAR KELİME HAVUZU BOŞ. Sahiplenilmemiş kelime kalmadı.';
            $lines[] = 'Bu durumda BOŞ liste döndür: {"topics":[]}';
        }

        $lines[] = '';
        $lines[] = 'YASAK — bunlar YENİ konu SAYILMAZ:';
        $lines[] = '  • Mevcut bir başlığın kelime sırasını, ekini veya çoğulunu değiştirmek';
        $lines[] = '    ("Ayder Gezilecek Yerler" varsa "Ayder\'de Gezilecek Yerler" YENİ DEĞİLDİR)';
        $lines[] = '  • Mevcut bir başlığa soru eki eklemek';
        $lines[] = '    ("Karadeniz Turu Fiyatları" varsa "Karadeniz Turu Fiyatları Nelerdir?" YENİ DEĞİLDİR)';
        $lines[] = '  • Aynı konuyu şu dolgu kelimeleriyle tekrarlamak:';
        $lines[] = '    faydaları, önemi, avantajları, verimlilik, stratejiler, ipuçları, çözümler,';
        $lines[] = '    rehber, nasıl artırılır, yolları, hakkında bilmeniz gerekenler';
        $lines[] = '  • Aynı ana anahtar kelimeyi hedefleyen ikinci bir yazı';
        $lines[] = '';
        $lines[] = 'UYGUN KONU YOKSA BOŞ LİSTE DÖNDÜR: {"topics":[]}';
        $lines[] = 'Zorlama. Boş liste, tekrar konudan daha iyidir.';
        $lines[] = '';
        $lines[] = 'Her konu için 4-7 maddelik bir outline (H2 başlık önerisi) ver.';
        $lines[] = 'Konular okuyucuya gerçek bir soru cevaplamalı; genel "faydaları" yazısı olmamalı.';

        return implode("\n", $lines);
    }
}
