<?php

namespace App\Services\Seo;

use App\Models\District;
use App\Models\Province;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Support\PathNormalizer;
use App\Support\TurkishText;

/**
 * İl/ilçe adı içeren anahtar kelimeleri üretir — DETERMİNİSTİK, AI kullanmaz.
 *
 * Bölge sayfaları "kalkış noktası" sayfalarıdır: ziyaretçi "rize günübirlik turlar" ya da
 * "ardeşen çıkışlı turlar" diye arar. YALNIZ ERİŞİLEBİLİR bölgeler için üretir: ilçe aktif VE
 * ili aktif olmalıdır. Kapalı bir bölge için kelime üretmek, 404 dönen bir sayfayı hedeflemektir.
 *
 * Sahiplik dağılımı (spec §3.1 — tek sahip kuralı):
 *   "{bölge} günübirlik turlar", "{bölge} çıkışlı turlar" → BÖLGE SAYFASI sahiplenir (ticari)
 *   "{bölge} çıkışlı {tur}"    → SAHİPSİZ bırakılır; blog üretim hattının hedefi olur
 *
 * Bölge × tur çarpımı YALNIZ öne çıkan turlar için üretilir. Bütün turları her
 * bölgeyle çarpmak, yazılamayacak kadar çok ve birbirine çok benzeyen konu üretir.
 */
class LocationKeywordBuilder
{
    private const NOTE_PREFIX = 'Bölge otomasyonu';

    /** Bölge sayfasının sahipleneceği ticari kalıplar. */
    private const OWNED_PATTERNS = [
        '%s günübirlik turlar' => 'COMMERCIAL_PRIMARY',
        '%s çıkışlı turlar' => 'COMMERCIAL_VARIANT',
        '%s tur firmaları' => 'COMMERCIAL_VARIANT',
    ];

    /** @return array{created: int, assigned: int, deactivated: int, regions: array<int, string>} */
    public function build(): array
    {
        $created = $assigned = 0;
        $regions = [];
        $activeHashes = [];
        $tourTerms = $this->featuredTourTerms();

        foreach ($this->reachableRegions() as $region) {
            $regions[] = $region['label'];
            $target = SeoTarget::query()->where('url_hash', PathNormalizer::hash($region['url']))->first();

            // TurkishText::searchLower kullanılır: mb_strtolower "İ" harfinde arkada
            // birleşik nokta (U+0307) bırakır ve kelime aranan hâlinden farklı olur.
            $name = TurkishText::searchLower($region['name']);

            // Ticari kalıplar — ilçe/il sayfası sahiplenir.
            foreach (self::OWNED_PATTERNS as $pattern => $type) {
                $keyword = sprintf($pattern, $name);
                $activeHashes[] = md5(TurkishText::lower($keyword));

                [$row, $isNew] = $this->upsert($keyword, [
                    'keyword_type' => $type,
                    'search_intent' => 'transactional',
                    'priority' => $region['type'] === 'district' ? 1 : 2,
                    'note' => self::NOTE_PREFIX.' · '.$region['label'],
                ]);

                $created += $isNew ? 1 : 0;

                // Sahipsizse hedefe ata; elle başka bir sahibe verilmişse DOKUNMA.
                if ($target && $target->status && ! $row->hasOwner()) {
                    $row->update(['target_id' => $target->getKey()]);
                    $assigned++;
                }
            }

            // Tur kalıpları — blog hattı için SAHİPSİZ kalır.
            foreach ($tourTerms as $term) {
                $keyword = $name.' çıkışlı '.$term;
                $activeHashes[] = md5(TurkishText::lower($keyword));

                [, $isNew] = $this->upsert($keyword, [
                    'keyword_type' => 'BLOG_PRIMARY',
                    'search_intent' => 'commercial',
                    'priority' => $region['type'] === 'district' ? 2 : 3,
                    'note' => self::NOTE_PREFIX.' · '.$region['label'],
                ]);

                $created += $isNew ? 1 : 0;
            }
        }

        // Bölge ya da tur kapatıldıysa kelimeleri pasife alınır — SİLİNMEZ (spec §10.9).
        $deactivated = SeoKeyword::query()
            ->where('note', 'like', self::NOTE_PREFIX.'%')
            ->whereNotIn('keyword_hash', $activeHashes)
            ->where('status', true)
            ->update(['status' => false]);

        return ['created' => $created, 'assigned' => $assigned, 'deactivated' => $deactivated, 'regions' => $regions];
    }

    /**
     * Erişilebilir bölgeler: ilçe için ili de aktif olmalı.
     *
     * @return array<int, array{name: string, label: string, url: string, type: string}>
     */
    public function reachableRegions(): array
    {
        $regions = [];

        foreach (Province::query()->where('is_active', true)->orderBy('sort_order')->get() as $province) {
            $regions[] = [
                'name' => $province->name,
                'label' => $province->name.' (il)',
                'url' => '/'.$province->slug,
                'type' => 'province',
            ];

            foreach (District::query()->where('province_id', $province->getKey())->where('is_active', true)->orderBy('sort_order')->get() as $district) {
                $regions[] = [
                    'name' => $district->name,
                    'label' => $district->name.' / '.$province->name,
                    'url' => '/'.$province->slug.'/'.$district->slug,
                    'type' => 'district',
                ];
            }
        }

        return $regions;
    }

    /** @return array<int, string> öne çıkan, yayındaki turların adları (küçük harf) */
    private function featuredTourTerms(): array
    {
        return Tour::query()->where('is_active', true)->where('is_featured', true)->orderBy('sort_order')
            ->pluck('title')
            ->map(fn (string $title) => TurkishText::searchLower($title))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{0: SeoKeyword, 1: bool} */
    private function upsert(string $keyword, array $attributes): array
    {
        $hash = md5(TurkishText::lower($keyword));
        $existing = SeoKeyword::query()->where('keyword_hash', $hash)->first();

        if ($existing) {
            // Kapalıyken tekrar aktifleşen bölgenin kelimesini geri aç.
            if (! $existing->status && str_starts_with((string) $existing->note, self::NOTE_PREFIX)) {
                $existing->update(['status' => true]);
            }

            return [$existing, false];
        }

        return [SeoKeyword::create(['keyword' => $keyword, 'status' => true] + $attributes), true];
    }
}
