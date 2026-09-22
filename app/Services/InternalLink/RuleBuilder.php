<?php

namespace App\Services\InternalLink;

use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\PathNormalizer;

/**
 * Aktif tur, kategori ve bölge sayfalarından iç link kuralı üretir — DETERMİNİSTİK.
 *
 * Anchor metinleri UYDURULMAZ: yalnız gerçek sayfa adlarından gelir.
 * Yalnız erişilebilir hedefler için kural açılır; kapalı sayfalar pasife alınır.
 */
class RuleBuilder
{
    /** @return array{created: int, deactivated: int} */
    public function build(): array
    {
        $created = 0;
        $activeHashes = [];

        // Tur sayfaları — başlık. "Ayder Yaylası Turu" yazılarda çoğu zaman aynen geçer.
        foreach (Tour::query()->where('is_active', true)->get() as $tour) {
            $created += $this->upsert($tour->title, '/'.$tour->slug, 'all', 70, $activeHashes);
        }

        // Kategori sayfaları — ad ("Günübirlik Turlar").
        foreach (TourCategory::query()->where('is_active', true)->get() as $category) {
            $created += $this->upsert($category->name, $category->path(), 'all', 55, $activeHashes);
        }

        // İl sayfaları — tam ifade ve yalın ad.
        foreach (Province::query()->where('is_active', true)->get() as $province) {
            $url = '/'.$province->slug;
            // Yalın bölge adı ("Trabzon") anchor OLMAZ: bir tur yazısında Trabzon çoğu zaman
            // kalkış noktası değil, gezilecek yer olarak geçer ve link yanlış sayfaya gider.
            $created += $this->upsert($province->name.' günübirlik turlar', $url, 'all', 52, $activeHashes);
            $created += $this->upsert($province->name.' çıkışlı turlar', $url, 'all', 50, $activeHashes);
            $created += $this->upsert($province->name.' çıkışlı', $url, 'all', 30, $activeHashes);
        }

        // İlçe sayfaları — yalnız ili de aktifse.
        foreach (District::query()->where('is_active', true)->with('province')->get() as $district) {
            if (! $district->province?->is_active) {
                continue;
            }

            $url = '/'.$district->province->slug.'/'.$district->slug;
            $created += $this->upsert($district->name.' çıkışlı turlar', $url, 'all', 60, $activeHashes);
            $created += $this->upsert($district->name.' çıkışlı', $url, 'all', 40, $activeHashes);
        }

        // Kaynağı kapanan kurallar pasife alınır — silinmez.
        $deactivated = InternalLinkRule::query()
            ->whereNotIn('target_hash', $activeHashes)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return ['created' => $created, 'deactivated' => $deactivated];
    }

    private function upsert(string $anchor, string $url, string $scope, int $priority, array &$hashes): int
    {
        $hash = PathNormalizer::hash($url);
        $hashes[] = $hash;

        // Hedef gerçekten var mı? (SeoTarget senkronu bunu doğrular)
        if (! SeoTarget::query()->where('url_hash', $hash)->where('status', true)->exists()) {
            return 0;
        }

        $existing = InternalLinkRule::query()->where('anchor_text', $anchor)->where('target_hash', $hash)->first();

        if ($existing) {
            $existing->is_active || $existing->update(['is_active' => true]);

            return 0;
        }

        InternalLinkRule::create([
            'anchor_text' => $anchor,
            'target_url' => $url,
            'scope_type' => $scope,
            'priority' => $priority,
            'max_per_article' => 1,
            'is_active' => true,
        ]);

        return 1;
    }
}
