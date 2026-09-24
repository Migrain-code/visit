<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTourDepartures extends ListRecords
{
    protected static string $resource = TourDepartureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni tur'),
        ];
    }
}
