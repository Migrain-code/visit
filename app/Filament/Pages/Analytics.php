<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RequestTrendChart;
use App\Filament\Widgets\SearchTrafficChart;
use App\Models\AiCrawlerVisit;
use App\Models\Blog;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\ReservationRequest;
use App\Models\SeoAnalysis;
use App\Models\SeoSearchQuery;
use App\Services\Analytics\BotSummary;
use App\Services\Google\GoogleClient;
use App\Services\Seo\ContentRegistry;
use App\Support\ChartPalette;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class Analytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'Analitik';

    protected static ?string $title = 'Analitik';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.analytics';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->isEditor() || $u?->isOperations());
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RequestTrendChart::class,
            SearchTrafficChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getViewData(): array
    {
        return [
            'tiles' => $this->tiles(),
            'bots' => app(BotSummary::class)->cards(),
            'botTotals' => app(BotSummary::class)->totals(),
            'scores' => $this->scoreByType(),
            'upcoming' => Blog::query()->where('status', Blog::STATUS_DRAFT)->whereNotNull('publish_at')
                ->where('publish_at', '>=', now())->orderBy('publish_at')->limit(7)->get(),
            'topPosts' => Blog::query()->published()->orderByDesc('views')->limit(5)->get(),
            'notFound' => NotFoundLog::query()->unresolved()->orderByDesc('hits')->limit(5)->get(),
            'googleReady' => app(GoogleClient::class)->isConfigured(),
            'search' => $this->searchSummary(),
        ];
    }

    /** Tek başına anlamlı rakamlar — grafik değil, sayı kutusu (bkz. form seçimi). */
    private function tiles(): array
    {
        $since = Carbon::today()->subDays(29);

        $quotes = ReservationRequest::query()->where('created_at', '>=', $since)->count();
        $quotesPrev = ReservationRequest::query()
            ->whereBetween('created_at', [$since->copy()->subDays(30), $since])
            ->count();

        $analyses = SeoAnalysis::query()->get();

        return [
            [
                'label' => 'Rezervasyon talebi',
                'value' => $quotes,
                'unit' => 'son 30 gün',
                'delta' => $this->delta($quotes, $quotesPrev),
                'icon' => 'heroicon-o-inbox-arrow-down',
                'color' => ChartPalette::PRIMARY,
            ],
            [
                'label' => 'Yayındaki blog',
                'value' => Blog::query()->published()->count(),
                'unit' => Blog::query()->where('status', Blog::STATUS_DRAFT)->whereNotNull('publish_at')->where('publish_at', '>=', now())->count().' yazı planlı',
                'delta' => null,
                'icon' => 'heroicon-o-newspaper',
                'color' => ChartPalette::LIGHT[2],
            ],
            [
                'label' => 'AI bot ziyareti',
                'value' => (int) AiCrawlerVisit::query()->sum('hits'),
                'unit' => AiCrawlerVisit::query()->distinct('bot')->count('bot').' farklı bot',
                'delta' => null,
                'icon' => 'heroicon-o-cpu-chip',
                'color' => ChartPalette::LIGHT[1],
            ],
            [
                'label' => 'Ortalama SEO skoru',
                'value' => $analyses->isEmpty() ? 0 : (int) round($analyses->avg('score')),
                'unit' => $analyses->count().' yayındaki sayfa',
                'delta' => null,
                'icon' => 'heroicon-o-chart-bar',
                'color' => ChartPalette::forScore($analyses->isEmpty() ? 0 : (int) round($analyses->avg('score'))),
            ],
            [
                'label' => 'Çözülmemiş 404',
                'value' => NotFoundLog::query()->unresolved()->count(),
                'unit' => Redirect::query()->where('is_active', true)->count().' aktif yönlendirme',
                'delta' => null,
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => NotFoundLog::query()->unresolved()->exists() ? ChartPalette::STATUS['warning'] : ChartPalette::STATUS['neutral'],
            ],
        ];
    }

    private function delta(int $now, int $before): ?array
    {
        if ($before === 0) {
            return $now > 0 ? ['direction' => 'up', 'text' => 'ilk dönem'] : null;
        }

        $change = (int) round((($now - $before) / $before) * 100);

        return [
            'direction' => $change >= 0 ? 'up' : 'down',
            'text' => ($change >= 0 ? '+' : '').$change.'% önceki 30 güne göre',
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function scoreByType(): Collection
    {
        return SeoAnalysis::query()->get()
            ->groupBy('content_type')
            ->map(fn (Collection $rows, string $type) => [
                'label' => ContentRegistry::TYPES[$type] ?? $type,
                'score' => (int) round($rows->avg('score')),
                'count' => $rows->count(),
                'color' => ChartPalette::forScore((int) round($rows->avg('score'))),
            ])
            ->sortByDesc('score')
            ->values();
    }

    private function searchSummary(): array
    {
        $rows = SeoSearchQuery::query()->pages()->get();

        return [
            'clicks' => (int) $rows->sum('clicks'),
            'impressions' => (int) $rows->sum('impressions'),
            'position' => $rows->isEmpty() ? null : round($rows->avg('position'), 1),
            'top_queries' => SeoSearchQuery::query()->queries()->orderByDesc('clicks')->limit(5)->get(),
        ];
    }
}
