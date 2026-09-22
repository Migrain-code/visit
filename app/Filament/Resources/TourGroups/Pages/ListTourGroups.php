<?php

namespace App\Filament\Resources\TourGroups\Pages;

use App\Filament\Resources\TourGroups\TourGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTourGroups extends ListRecords
{
    protected static string $resource = TourGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Grup kaydet'),
        ];
    }
}
