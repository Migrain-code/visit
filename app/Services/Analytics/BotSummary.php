<?php

namespace App\Services\Analytics;

use App\Models\AiCrawlerVisit;
use App\Support\ChartPalette;
use Illuminate\Support\Collection;

/**
 * AI bot ziyaretlerini bot başına TEK SATIRA indirger.
 *
 * Ham tablo (bot + adres) okunmaz: tek bir bot onlarca satıra dağılır ve
 * "hangi bot siteyi ne kadar okuyor?" sorusu görünmez hâle gelir.
 *
 * Renkler KİMLİĞE göre sabit sırayla atanır, döngüye sokulmaz; sekizden sonrası
 * nötr renge düşer (üretilmiş renk yok).
 */
class BotSummary
{
    /** @return Collection<int, array<string, mixed>> */
    public function cards(): Collection
    {
        $visits = AiCrawlerVisit::query()->get();

        if ($visits->isEmpty()) {
            return collect();
        }

        $total = max(1, (int) $visits->sum('hits'));

        return $visits->groupBy('bot')
            ->map(fn (Collection $rows, string $bot) => [
                'bot' => $bot,
                'hits' => (int) $rows->sum('hits'),
                'paths' => $rows->count(),
                'share' => (int) round(($rows->sum('hits') / $total) * 100),
                'last_seen' => $rows->max('last_seen_at'),
                'errors' => $rows->filter(fn ($r) => $r->last_status && $r->last_status >= 400)->count(),
                'top' => $rows->sortByDesc('hits')->take(3)->map(fn ($r) => [
                    'path' => $r->path,
                    'hits' => (int) $r->hits,
                    'status' => $r->last_status,
                ])->values(),
            ])
            ->sortByDesc('hits')
            ->values()
            ->map(function (array $row, int $i) {
                $row['color'] = ChartPalette::LIGHT[$i] ?? ChartPalette::STATUS['neutral'];

                return $row;
            });
    }

    /** @return array<string, mixed> */
    public function totals(): array
    {
        $visits = AiCrawlerVisit::query()->get();

        return [
            'hits' => (int) $visits->sum('hits'),
            'bots' => $visits->pluck('bot')->unique()->count(),
            'paths' => $visits->count(),
            'errors' => $visits->filter(fn ($r) => $r->last_status && $r->last_status >= 400)->sum('hits'),
            'last_seen' => $visits->max('last_seen_at'),
        ];
    }
}
