<?php

namespace App\Services\Seo;

use App\Models\District;
use App\Models\Page;
use App\Models\Province;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\PathNormalizer;

/**
 * Mevcut içerikten SEO hedef sayfalarını üretir/günceller (spec §3.1).
 *
 * Hedefler UYDURULMAZ: yalnız gerçekten var olan sayfalardan türetilir.
 * Elle eklenen "custom" hedeflere dokunulmaz.
 */
class TargetSynchroniser
{
    /** @return array{created: int, updated: int, deactivated: int} */
    public function sync(): array
    {
        $created = $updated = 0;
        $seen = [];

        $rows = [
            ['home', 'Ana Sayfa', '/', null, null, true],
            ['category', 'Turlar (liste)', '/turlar', null, null, true],
            ['page', 'Tur Takvimi', '/tur-takvimi', null, null, true],
        ];

        foreach (Tour::all() as $tour) {
            $rows[] = ['tour', $tour->title, '/'.$tour->slug, 'tour', $tour->getKey(), (bool) $tour->is_active];
        }

        foreach (TourCategory::all() as $category) {
            $rows[] = ['category', $category->name, $category->path(), 'category', $category->getKey(), (bool) $category->is_active];
        }

        foreach (Province::all() as $province) {
            $rows[] = ['province', $province->name.' (il)', '/'.$province->slug, 'province', $province->getKey(), (bool) $province->is_active];
        }

        foreach (District::with('province')->get() as $district) {
            if (! $district->province) {
                continue;
            }

            $rows[] = [
                'district',
                $district->name.' / '.$district->province->name,
                '/'.$district->province->slug.'/'.$district->slug,
                'district',
                $district->getKey(),
                (bool) $district->is_active && (bool) $district->province->is_active,
            ];
        }

        foreach (Page::all() as $page) {
            $rows[] = ['page', $page->title, '/'.$page->slug, 'page', $page->getKey(), (bool) $page->is_active];
        }

        foreach ($rows as [$type, $name, $url, $contentType, $contentId, $active]) {
            $hash = PathNormalizer::hash($url);
            $seen[] = $hash;

            $target = SeoTarget::firstOrNew(['url_hash' => $hash]);
            $exists = $target->exists;

            $target->fill([
                'name' => $name,
                'url' => $url,
                'target_type' => $type,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'status' => $active,
            ])->save();

            $exists ? $updated++ : $created++;
        }

        // Kaynağı kalmayan otomatik hedefler pasife alınır — SİLİNMEZ (spec §10.9).
        $deactivated = SeoTarget::query()
            ->where('target_type', '!=', 'custom')
            ->whereNotIn('url_hash', $seen)
            ->where('status', true)
            ->update(['status' => false]);

        return ['created' => $created, 'updated' => $updated, 'deactivated' => $deactivated];
    }
}
