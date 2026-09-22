<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\TourGroups\TourGroupResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTourDeparture extends ViewRecord
{
    protected static string $resource = TourDepartureResource::class;

    protected static ?string $navigationLabel = 'Özet';

    public function getTitle(): string
    {
        return $this->getRecord()->label;
    }

    public function getSubheading(): ?string
    {
        return 'Sefer kodu: '.$this->getRecord()->code;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addGroup')
                ->label('Grup kaydet')
                ->icon('heroicon-o-user-plus')
                ->url(fn () => TourGroupResource::getUrl('create', ['departure' => $this->getRecord()->getKey()]))
                ->visible(fn () => (auth()->user()?->registersGroups() ?? false) && $this->getRecord()->status->acceptsGroups()),
            Action::make('manifest')
                ->label('Yolcu listesi')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.manifest', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
