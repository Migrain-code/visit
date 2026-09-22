<?php

namespace App\Filament\Resources\TourCategories\Pages;

use App\Filament\Resources\TourCategories\TourCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTourCategory extends EditRecord
{
    protected static string $resource = TourCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
