<?php

namespace App\Services\Seo;

use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Support\PathNormalizer;
use App\Support\TurkishText;

/**
 * Ticari kelimeleri ilgili TUR sayfasına atar — DETERMİNİSTİK (spec §3.1).
 *
 * Neden gerekli: sahipsiz bir ticari kelime blog üretim hattının havuzuna düşer ve
 * blog, para kazandıran tur sayfasıyla aynı kelimeyi hedefleyerek onu yer
 * (cannibalization). Ticari kelimelerin sahibi tur sayfası olmalıdır.
 *
 * Eşleştirme UYDURMAZ. Bir tur şu iki durumdan birinde eşleşir:
 *   1. Adındaki ayırt edici kelimelerin TAMAMI kelimede geçiyorsa
 *      ("kimlikle batum turu" → Günübirlik Batum Turu), ya da
 *   2. YALNIZ o tura ait bir kelime geçiyorsa ("pokut yaylası turu fiyatları" →
 *      Pokut ve Sal Yaylası Turu; "pokut" başka hiçbir turun adında yoktur).
 * "batum" iki turun adında geçtiği için tek başına "Batum Tiflis Turu"nu seçmez.
 * Birden çok tur eşleşirse en çok kelimesi tutan kazanır; eşitlikte kelimeye
 * DOKUNULMAZ (sahipsiz kalır).
 */
class CommercialKeywordAssigner
{
    /** Tur adlarında ayırt edici OLMAYAN kelimeler. */
    private const GENERIC = [
        'tur', 'turu', 'turları', 'turlar', 'gezi', 'gezisi', 'gezileri', 'günübirlik', 'gunubirlik',
        'gece', 'gün', 'günlük', 'konaklamalı', 've', 'ile', 'çıkışlı', 'özel', 'büyük', 'yeni',
        'kültür', 'doğa', 'hafta', 'sonu', 'haftasonu', 'yurt', 'dışı', 'içi', 'vizesiz',
        'yayla', 'yaylası', 'yaylaları', 'şelalesi', 'mağarası', 'manastırı', 'gölü', 'batımı', 'kimlikle',
    ];

    /** @return array{assigned: int, skipped: int, details: array<int, string>} */
    public function assign(): array
    {
        $tours = Tour::query()->where('is_active', true)->get()
            ->map(fn (Tour $tour) => ['slug' => $tour->slug, 'terms' => $this->distinctiveTerms($tour->title)])
            ->filter(fn (array $tour) => $tour['terms'] !== [])
            ->values();

        // Yalnız TEK bir turun adında geçen kelimeler o turu tek başına işaret eder.
        $usage = $tours->flatMap(fn (array $tour) => $tour['terms'])->countBy();
        $tours = $tours->map(fn (array $tour) => $tour + [
            'unique' => array_values(array_filter($tour['terms'], fn (string $term) => $usage[$term] === 1)),
        ]);

        $assigned = 0;
        $skipped = 0;
        $details = [];

        $candidates = SeoKeyword::query()
            ->active()
            ->unassigned()
            ->whereIn('keyword_type', ['COMMERCIAL_PRIMARY', 'COMMERCIAL_VARIANT'])
            ->get();

        foreach ($candidates as $keyword) {
            $slug = $this->matchTour($keyword->keyword, $tours->all());

            if (! $slug) {
                $skipped++;

                continue;
            }

            $target = SeoTarget::query()
                ->where('url_hash', PathNormalizer::hash('/'.$slug))
                ->where('status', true)
                ->first();

            if (! $target) {
                $skipped++;

                continue;
            }

            $keyword->update(['target_id' => $target->getKey()]);
            $assigned++;
            $details[] = $keyword->keyword.' → '.$target->name;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped, 'details' => $details];
    }

    /** @return array<int, string> */
    public function distinctiveTerms(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', TurkishText::searchLower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::GENERIC, true) && ! ctype_digit($word),
        )));
    }

    /** @param array<int, array{slug: string, terms: array<int, string>, unique: array<int, string>}> $tours */
    private function matchTour(string $keyword, array $tours): ?string
    {
        $needle = ' '.TurkishText::lower($keyword).' ';
        // Kelime başı eşleşmesi: "efes" kelimesi "nefes" içinde eşleşmesin.
        $has = fn (string $term): bool => str_contains($needle, ' '.$term);

        $best = null;
        $bestCount = 0;
        $tie = false;

        foreach ($tours as $tour) {
            $matched = count(array_filter($tour['terms'], $has));
            $allMatch = $matched === count($tour['terms']);
            $uniqueMatch = array_filter($tour['unique'] ?? [], $has) !== [];

            if (! $allMatch && ! $uniqueMatch) {
                continue;
            }

            if ($matched > $bestCount) {
                [$best, $bestCount, $tie] = [$tour['slug'], $matched, false];
            } elseif ($matched === $bestCount) {
                $tie = true;
            }
        }

        return $tie ? null : $best;
    }
}
