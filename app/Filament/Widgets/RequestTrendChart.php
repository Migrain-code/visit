<?php

namespace App\Filament\Widgets;

use App\Models\ReservationRequest;
use App\Support\ChartPalette;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Rezervasyon taleplerinin günlük seyri.
 *
 * TEK SERİ: gösterge kutusu yok, başlık zaten seriyi adlandırıyor.
 * İkinci bir ölçüyü (ör. görüntülenme) aynı grafiğe İKİNCİ EKSENLE eklemeyin —
 * iki ölçek tek grafikte yanıltıcıdır; gerekirse ayrı bir grafik açın.
 */
class RequestTrendChart extends ChartWidget
{
    protected ?string $heading = 'Rezervasyon talepleri — son 30 gün';

    protected ?string $description = 'Sitenin ana dönüşüm ölçüsü. Tur sayfaları ve iletişim formundan gelen talepler.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected static ?int $sort = 4;

    /** Panoda ve Analitik sayfasında görünür; rehber talepleri görmez. */
    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->registersGroups() || $user?->isEditor());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = Carbon::today()->subDays(29);

        $counts = ReservationRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($r) => $r->created_at->toDateString())
            ->map->count();

        $labels = [];
        $values = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->translatedFormat('d M');
            $values[] = $counts->get($date->toDateString(), 0);
        }

        return [
            'datasets' => [[
                'label' => 'Rezervasyon talebi',
                'data' => $values,
                'backgroundColor' => ChartPalette::PRIMARY,
                'hoverBackgroundColor' => ChartPalette::PRIMARY_DARK,
                'borderRadius' => 4,   // veri ucu yuvarlatılır, taban düz kalır
                'borderSkipped' => 'bottom',
                'barPercentage' => 0.72,
                'categoryPercentage' => 0.86,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false], // tek seri → gösterge gereksiz
                'tooltip' => ['displayColors' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['drawBorder' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxRotation' => 0, 'autoSkipPadding' => 16],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
