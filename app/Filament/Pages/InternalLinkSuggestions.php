<?php

namespace App\Filament\Pages;

use App\Models\Blog;
use App\Models\InternalLinkRule;
use App\Models\InternalLinkSuggestionReject;
use App\Services\InternalLink\LinkApplier;
use App\Services\InternalLink\SuggestionEngine;
use App\Support\PathNormalizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * İç link öneri ekranı (spec §3.6).
 *
 * Öneriler DETERMİNİSTİK üretilir. Reddedilen bir öneri (blog + hedef + anchor)
 * bir daha çıkmaz.
 */
class InternalLinkSuggestions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'İç Link Önerileri';

    protected static ?string $title = 'İç Link Önerileri';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.internal-link-suggestions';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getViewData(): array
    {
        $applier = app(LinkApplier::class);

        return [
            'enabled' => $applier->enabled(),
            'maxPerArticle' => $applier->maxPerArticle(),
            'suggestions' => app(SuggestionEngine::class)->all(50),
            'rules' => InternalLinkRule::query()->count(),
        ];
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Kural oluştur')
            ->icon('heroicon-o-check')
            ->color('success')
            ->action(function (array $arguments) {
                InternalLinkRule::updateOrCreate(
                    [
                        'anchor_text' => $arguments['anchor'],
                        'target_hash' => PathNormalizer::hash($arguments['target']),
                    ],
                    [
                        'target_url' => $arguments['target'],
                        'scope_type' => 'blog',
                        'priority' => (int) ($arguments['score'] ?? 50),
                        'max_per_article' => 1,
                        'is_active' => true,
                    ],
                );

                Notification::make()
                    ->title('Kural oluşturuldu')
                    ->body('"'.$arguments['anchor'].'" → '.$arguments['target'].
                        (app(LinkApplier::class)->enabled() ? '' : ' · Motor kapalı olduğu için link henüz basılmıyor.'))
                    ->success()->send();
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reddet')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->action(function (array $arguments) {
                InternalLinkSuggestionReject::remember(
                    (int) $arguments['blog'],
                    $arguments['target'],
                    $arguments['anchor'],
                );

                Notification::make()->title('Öneri reddedildi')->body('Bu öneri bir daha gösterilmeyecek.')->success()->send();
            });
    }
}
