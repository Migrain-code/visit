<?php

namespace App\Filament\Widgets;

use App\Models\SeoSearchQuery;
use App\Services\Google\GoogleClient;
use App\Support\ChartPalette;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Google aramasından gelen tıklamalar.
 *
 * DİKKAT: gösterim (impressions) ile tıklama (clicks) farklı büyüklükte ölçülerdir.
 * İkisini tek grafikte İKİ AYRI EKSENLE göstermek yanıltıcıdır; gösterim ayrı bir
 * kutuda toplam olarak verilir.
 */
class SearchTrafficChart extends ChartWidget
{
    protected ?string $heading = 'Google aramasından tıklama';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        // Kimlik yoksa grafik hiç gösterilmez; boş eksen kafa karıştırır.
        return app(GoogleClient::class)->isConfigured();
    }

    public function getDescription(): ?string
    {
        $row = SeoSearchQuery::query()->pages()->orderByDesc('period_end')->first();

        return $row
            ? 'Dönem: '.$row->period_start->format('d.m.Y').' – '.$row->period_end->format('d.m.Y')
            : 'Henüz veri yok. Terminalde: php artisan seo:sync-search-console';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = SeoSearchQuery::query()
            ->pages()
            ->orderBy('period_end')
            ->get(['period_end', 'clicks'])
            ->groupBy(fn ($r) => $r->period_end->toDateString())
            ->map(fn ($g) => $g->sum('clicks'));

        return [
            'datasets' => [[
                'label' => 'Tıklama',
                'data' => $rows->values()->all(),
                'borderColor' => ChartPalette::PRIMARY,
                'backgroundColor' => 'rgba(42, 120, 214, 0.12)',
                'borderWidth' => 2,      // ince çizgi
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'pointBackgroundColor' => ChartPalette::PRIMARY,
                'fill' => true,
                'tension' => 0.25,
            ]],
            'labels' => $rows->keys()->map(fn ($d) => Carbon::parse($d)->translatedFormat('d M'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false]],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'maintainAspectRatio' => false,
        ];
    }
}
