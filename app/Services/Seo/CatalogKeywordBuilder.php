<?php

namespace App\Services\Seo;

use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\PathNormalizer;
use App\Support\TurkishText;

/**
 * Tur ve kategori adlarından anahtar kelime üretir — DETERMİNİSTİK, AI kullanmaz.
 *
 * Ticari kelimelerin sahibi para kazandıran sayfadır (spec §3.1 — tek sahip kuralı):
 *   "{tur}", "{tur} fiyatları", "{tur} programı"   → TUR SAYFASI sahiplenir
 *   "{kategori}", "{kategori} fiyatları"           → KATEGORİ SAYFASI sahiplenir
 *
 * Sahipsiz kalsalardı blog üretim hattının havuzuna düşer ve blog, tur sayfasıyla
 * aynı kelimeyi hedefleyerek onu yerdi (cannibalization).
 */
class CatalogKeywordBuilder
{
    private const NOTE_PREFIX = 'Katalog otomasyonu';

    private const TOUR_PATTERNS = [
        '%s' => 'COMMERCIAL_PRIMARY',
        '%s fiyatları' => 'COMMERCIAL_VARIANT',
        '%s programı' => 'COMMERCIAL_VARIANT',
    ];

    private const CATEGORY_PATTERNS = [
        '%s' => 'COMMERCIAL_PRIMARY',
        '%s fiyatları' => 'COMMERCIAL_VARIANT',
    ];

    /** @return array{created: int, assigned: int, deactivated: int, items: array<int, string>} */
    public function build(): array
    {
        $created = $assigned = 0;
        $items = [];
        $activeHashes = [];

        $sources = collect()
            ->merge(Tour::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (Tour $t) => [$t->title, '/'.$t->slug, self::TOUR_PATTERNS, 1]))
            ->merge(TourCategory::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (TourCategory $c) => [$c->name, $c->path(), self::CATEGORY_PATTERNS, 2]));

        foreach ($sources as [$label, $path, $patterns, $priority]) {
            $items[] = $label;
            $target = SeoTarget::query()->where('url_hash', PathNormalizer::hash($path))->first();

            // DİKKAT: mb_strtolower "İstanbul" → "i̇stanbul" üretir (birleşik nokta U+0307
            // kalır) ve kelime aranan hâlinden farklı kaydedilir.
            $name = TurkishText::searchLower($label);

            foreach ($patterns as $pattern => $type) {
                $keyword = sprintf($pattern, $name);
                $activeHashes[] = md5(TurkishText::lower($keyword));

                [$row, $isNew] = $this->upsert($keyword, [
                    'keyword_type' => $type,
                    'search_intent' => 'transactional',
                    'priority' => $priority,
                    'note' => self::NOTE_PREFIX.' · '.$label,
                ]);

                $created += $isNew ? 1 : 0;

                // Sahipsizse ata; elle YAŞAYAN başka bir sahibe verilmişse DOKUNMA.
                if ($target && $target->status && $this->needsOwner($row, $target)) {
                    $row->update(['target_id' => $target->getKey()]);
                    $assigned++;
                }
            }
        }

        // Tur ya da kategori kapatıldıysa kelimeleri pasife alınır — SİLİNMEZ (spec §10.9).
        $deactivated = SeoKeyword::query()
            ->where('note', 'like', self::NOTE_PREFIX.'%')
            ->whereNotIn('keyword_hash', $activeHashes)
            ->where('status', true)
            ->update(['status' => false]);

        return ['created' => $created, 'assigned' => $assigned, 'deactivated' => $deactivated, 'items' => $items];
    }

    /** Sahibi yok ya da sahibi artık yayında olmayan bir hedef mi? */
    private function needsOwner(SeoKeyword $row, SeoTarget $target): bool
    {
        if (! $row->hasOwner()) {
            return true;
        }

        if ($row->owner_blog_id || $row->target_id === $target->getKey()) {
            return false;
        }

        return ! SeoTarget::query()->whereKey($row->target_id)->where('status', true)->exists();
    }

    /** @return array{0: SeoKeyword, 1: bool} */
    private function upsert(string $keyword, array $attributes): array
    {
        $hash = md5(TurkishText::lower($keyword));
        $existing = SeoKeyword::query()->where('keyword_hash', $hash)->first();

        if ($existing) {
            if (! $existing->status && str_starts_with((string) $existing->note, self::NOTE_PREFIX)) {
                $existing->update(['status' => true]);
            }

            return [$existing, false];
        }

        return [SeoKeyword::create(['keyword' => $keyword, 'status' => true] + $attributes), true];
    }
}
