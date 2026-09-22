<?php

namespace App\Filament\Pages;

use App\Models\AiGeneration;
use App\Models\Blog;
use App\Models\NotFoundLog;
use App\Models\SeoAnalysis;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\DuplicateScanner;
use App\Services\Seo\KeywordPool;
use App\Support\SeoConfig;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class SeoDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'SEO Gösterge Paneli';

    protected static ?string $title = 'SEO Gösterge Paneli';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.seo-dashboard';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->isEditor());
    }

    public function getViewData(): array
    {
        $target = SeoConfig::int('score_target', 80, 1, 100);
        $analyses = SeoAnalysis::query()->get();

        return [
            'target' => $target,
            'total' => $analyses->count(),
            'average' => $analyses->isEmpty() ? 0 : (int) round($analyses->avg('score')),
            'below' => $analyses->where('score', '<', $target)->count(),
            'lastRun' => $analyses->max('analyzed_at'),
            'byType' => $analyses->groupBy('content_type')->map(fn (Collection $rows) => [
                'label' => ContentRegistry::TYPES[$rows->first()->content_type] ?? $rows->first()->content_type,
                'count' => $rows->count(),
                'average' => (int) round($rows->avg('score')),
                'below' => $rows->where('score', '<', $target)->count(),
            ])->sortByDesc('count'),
            // Sayımlar SABİT sorun metinleriyle eşleşir (spec §10.4).
            'issues' => $analyses->flatMap(fn (SeoAnalysis $a) => $a->issues ?? [])
                ->countBy()->sortDesc(),
            'worst' => $analyses->sortBy('score')->take(10),
            'indexStatus' => $analyses->whereNotNull('google_index_status')->countBy('google_index_status'),
            'keywordStats' => app(KeywordPool::class)->stats(),
            'duplicatePairs' => Blog::query()->count() > 1 ? app(DuplicateScanner::class)->pairs()->count() : 0,
            'notFound' => NotFoundLog::query()->unresolved()->count(),
            'aiFailures' => AiGeneration::query()->failed()->where('created_at', '>=', now()->subDays(7))->count(),
            'blogStats' => [
                'published' => Blog::query()->published()->count(),
                'draft' => Blog::query()->where('status', Blog::STATUS_DRAFT)->whereNull('merged_into_id')->count(),
                'merged' => Blog::query()->whereNotNull('merged_into_id')->count(),
            ],
        ];
    }
}
