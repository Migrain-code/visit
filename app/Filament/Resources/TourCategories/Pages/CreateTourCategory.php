<?php

namespace App\Filament\Resources\TourCategories\Pages;

use App\Filament\Resources\TourCategories\TourCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTourCategory extends CreateRecord
{
    protected static string $resource = TourCategoryResource::class;
}
