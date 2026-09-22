<?php

namespace App\Filament\Resources\TourGroups\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\TourGroups\TourGroupResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTourGroup extends ViewRecord
{
    protected static string $resource = TourGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('departure')
                ->label('Sefere git')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(fn () => TourDepartureResource::getUrl('view', ['record' => $this->record->tour_departure_id])),
            EditAction::make(),
        ];
    }
}
