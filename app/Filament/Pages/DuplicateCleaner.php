<?php

namespace App\Filament\Pages;

use App\Models\Blog;
use App\Services\Seo\DuplicateMerger;
use App\Services\Seo\DuplicateScanner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Yayınlanmış tekrarları temizleme ekranı (spec §3.5).
 *
 * Tarama eşiğini geçen çiftler LİSTELENİR ama BUTON ÇIKMAZ.
 * Yalnız birleştirme eşiğini geçenler birleştirilebilir.
 */
class DuplicateCleaner extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'Çakışan İçerikler';

    protected static ?string $title = 'Çakışan İçerikler';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.duplicate-cleaner';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (Blog::query()->count() < 2) {
            return null;
        }

        $count = app(DuplicateScanner::class)->pairs()->where('mergeable', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getViewData(): array
    {
        $scanner = app(DuplicateScanner::class);

        return [
            'pairs' => $scanner->pairs(),
            'scanThreshold' => $scanner->scanThreshold(),
            'mergeThreshold' => $scanner->mergeThreshold(),
        ];
    }

    public function mergeAction(): Action
    {
        return Action::make('merge')
            ->label('Birleştir')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Yazıları birleştir')
            ->modalDescription('Kaybeden yazı SİLİNMEZ: yayından kaldırılır ve adresi 301 ile kazanana yönlendirilir. Karar yanlışsa yazıyı tekrar yayınlamanız yeterli.')
            ->modalSubmitActionLabel('Birleştir')
            ->action(function (array $arguments, DuplicateMerger $merger) {
                $winner = Blog::find($arguments['winner'] ?? null);
                $loser = Blog::find($arguments['loser'] ?? null);

                if (! $winner || ! $loser) {
                    Notification::make()->title('Yazı bulunamadı')->danger()->send();

                    return;
                }

                try {
                    $result = $merger->merge($winner, $loser);
                } catch (Throwable $e) {
                    Notification::make()->title('Birleştirilemedi')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                if (! $result['merged']) {
                    // İdempotans: ikinci çağrı HATA DEĞİL (spec §3.5).
                    Notification::make()->title('Bu çift zaten birleştirilmiş')->info()->send();

                    return;
                }

                Notification::make()
                    ->title('Birleştirildi')
                    ->body('"'.$loser->title.'" yayından kaldırıldı ve 301 ile "'.$winner->title.'" adresine yönlendirildi.')
                    ->success()->send();
            });
    }

    public function undoAction(): Action
    {
        return Action::make('undo')
            ->label('Geri al')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (array $arguments, DuplicateMerger $merger) {
                $blog = Blog::find($arguments['blog'] ?? null);

                if ($blog && $merger->undo($blog)) {
                    Notification::make()->title('Geri alındı')->body('Yazı tekrar yayınlandı, yönlendirme pasife alındı.')->success()->send();
                }
            });
    }
}
