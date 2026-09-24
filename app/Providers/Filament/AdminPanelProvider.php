<?php

namespace App\Providers\Filament;

use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Filament\Widgets\OperationStatsWidget;
use App\Filament\Widgets\PassengerTrendChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Yönetim paneli.
 *
 * Herkes telefondan kullanır: alt sekme çubuğu (mobil), ana ekrana eklenebilen
 * uygulama (PWA manifest) ve tek ekrana sığan sade menü.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName(fn () => site_name())
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                'Operasyon',
                'Personel',
                'Rapor',
                'Ayarlar',
            ])
            ->navigationItems([
                // Personelin en sık kullandığı ekran: menüde ayrı bir madde olarak durur.
                NavigationItem::make('Yolcu Ekle')
                    ->url(fn () => TourGroupResource::getUrl('create'))
                    ->icon('heroicon-o-user-plus')
                    ->group('Operasyon')
                    ->sort(2)
                    ->visible(fn () => auth()->user()?->registersGroups() ?? false),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                OperationStatsWidget::class,
                PassengerTrendChart::class,
            ])
            ->renderHook(PanelsRenderHook::HEAD_START, fn () => Blade::render('@include(\'filament.partials.pwa-head\')'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => Blade::render('@include(\'filament.partials.mobile-tabs\')'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
