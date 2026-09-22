<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\District;
use App\Models\Page;
use App\Models\Province;
use App\Models\SeoKeyword;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * AI konu üreticisine verilecek kelime havuzu (spec §7.3).
 *
 * TUZAK: Havuza kullanılmış kelimeleri koymak, hakkında zaten 30 yazı olan kelimenin
 * tekrar tekrar önerilmesine yol açar. Havuza YALNIZ sahiplenilmemiş kelimeler girer.
 *
 * Eleme iki ölçüt:
 *   (a) sahip alanı dolu (target_id veya owner_blog_id)
 *   (b) kelime metni mevcut bir içeriğin başlık veya metasında geçiyor
 *
 * PERFORMANS: içerikler TEK SEFERDE yüklenir. Kelime başına tam tarama yapmak
 * 159 kelimede 636 sorgu demektir (spec §7.3).
 */
class KeywordPool
{
    /** @var Collection<int, string>|null Normalleştirilmiş tüm içerik metinleri */
    private ?Collection $haystack = null;

    /** @return Collection<int, SeoKeyword> */
    public function available(?int $limit = null): Collection
    {
        $keywords = SeoKeyword::query()->active()->unassigned()->orderBy('priority')->orderByDesc('search_volume')->get();

        $free = $keywords->reject(fn (SeoKeyword $k) => $this->isCovered($k->keyword));

        return $limit ? $free->take($limit)->values() : $free->values();
    }

    /** @return array{total: int, assigned: int, covered: int, free: int} */
    public function stats(): array
    {
        $total = SeoKeyword::query()->active()->count();
        $unassigned = SeoKeyword::query()->active()->unassigned()->get();
        $covered = $unassigned->filter(fn (SeoKeyword $k) => $this->isCovered($k->keyword))->count();

        return [
            'total' => $total,
            'assigned' => $total - $unassigned->count(),
            'covered' => $covered,
            'free' => $unassigned->count() - $covered,
        ];
    }

    /** Kelime mevcut bir içeriğin başlık/metasında geçiyor mu? */
    public function isCovered(string $keyword): bool
    {
        $needle = TurkishText::lower(trim($keyword));

        if ($needle === '') {
            return true;
        }

        foreach ($this->haystack() as $text) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, string> */
    private function haystack(): Collection
    {
        if ($this->haystack !== null) {
            return $this->haystack;
        }

        $parts = collect();

        // TEK SEFERDE yükle — kelime başına sorgu ATMA.
        foreach (Blog::query()->get(['title', 'meta_title', 'meta_description', 'primary_keyword']) as $row) {
            $parts->push($row->title, $row->meta_title, $row->meta_description, $row->primary_keyword);
        }

        foreach (Tour::query()->get(['title', 'meta_title', 'meta_description']) as $row) {
            $parts->push($row->title, $row->meta_title, $row->meta_description);
        }

        foreach (TourCategory::query()->get(['name', 'meta_title', 'meta_description']) as $row) {
            $parts->push($row->name, $row->meta_title, $row->meta_description);
        }

        foreach (Province::query()->get(['name', 'meta_title', 'meta_description']) as $row) {
            $parts->push($row->name, $row->meta_title, $row->meta_description);
        }

        foreach (District::query()->get(['name', 'meta_title', 'meta_description']) as $row) {
            $parts->push($row->name, $row->meta_title, $row->meta_description);
        }

        foreach (Page::query()->get(['title', 'meta_title', 'meta_description']) as $row) {
            $parts->push($row->title, $row->meta_title, $row->meta_description);
        }

        return $this->haystack = $parts
            ->filter()
            ->map(fn ($text) => TurkishText::lower((string) $text))
            ->unique()
            ->values();
    }
}
