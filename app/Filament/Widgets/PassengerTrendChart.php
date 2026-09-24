<?php

namespace App\Filament\Widgets;

use App\Models\TourDeparture;
use App\Support\ChartPalette;
use Filament\Widgets\ChartWidget;

/**
 * Son turların yolcu sayısı: tek seri, gösterge kutusu yok.
 */
class PassengerTrendChart extends ChartWidget
{
    protected ?string $heading = 'Turlara göre yolcu';

    protected ?string $description = 'Yapılmış ve yaklaşan son 10 tur.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected static ?int $sort = 2;

    /** İlk boyamada hazır olsun: telefonda ikinci bir istek beklenmez. */
    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $tours = TourDeparture::query()
            ->visibleTo(auth()->user())
            ->withSeatStats()
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('starts_at')
            ->limit(10)
            ->get()
            ->sortBy('starts_at')
            ->values();

        return [
            'datasets' => [[
                'label' => 'Yolcu',
                'data' => $tours->map(fn (TourDeparture $d) => $d->seats_taken)->all(),
                'backgroundColor' => ChartPalette::PRIMARY,
                'hoverBackgroundColor' => ChartPalette::PRIMARY_DARK,
                'borderRadius' => 4,
                'borderSkipped' => 'bottom',
                'barPercentage' => 0.72,
                'categoryPercentage' => 0.86,
            ]],
            'labels' => $tours->map(fn (TourDeparture $d) => $d->starts_at->format('d.m').' '.\Illuminate\Support\Str::limit($d->title, 18))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['displayColors' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['drawBorder' => false]],
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxRotation' => 0, 'autoSkipPadding' => 12]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
