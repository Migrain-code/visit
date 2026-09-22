<?php

namespace App\Filament\Resources\AiCrawlerVisits\Pages;

use App\Filament\Pages\Analytics;
use App\Filament\Resources\AiCrawlerVisits\AiCrawlerVisitResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageAiCrawlerVisits extends ManageRecords
{
    protected static string $resource = AiCrawlerVisitResource::class;

    public function getSubheading(): ?string
    {
        return 'Hangi yapay zeka ajanının siteden ne okuduğunu gösterir. Aşağıdaki tablo ham kayıtlardır; '
            .'üstteki kartlar bot başına toplamı verir.';
    }

    /** Tablonun üstüne bot başına toplu kartları basar. */
    public function getHeader(): ?View
    {
        return view('filament.partials.bot-cards-header');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analytics')
                ->label('Analitik paneli')
                ->icon('heroicon-o-presentation-chart-line')
                ->color('gray')
                ->url(Analytics::getUrl()),
        ];
    }
}
