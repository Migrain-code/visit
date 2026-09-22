<?php

namespace App\Filament\Resources\TourCategories\Pages;

use App\Filament\Resources\TourCategories\TourCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTourCategories extends ListRecords
{
    protected static string $resource = TourCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
