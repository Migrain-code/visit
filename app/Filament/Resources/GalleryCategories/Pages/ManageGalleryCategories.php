<?php

namespace App\Filament\Resources\GalleryCategories\Pages;

use App\Filament\Resources\GalleryCategories\GalleryCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGalleryCategories extends ManageRecords
{
    protected static string $resource = GalleryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
