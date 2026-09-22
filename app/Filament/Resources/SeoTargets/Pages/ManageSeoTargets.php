<?php

namespace App\Filament\Resources\SeoTargets\Pages;

use App\Filament\Resources\SeoTargets\SeoTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoTargets extends ManageRecords
{
    protected static string $resource = SeoTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
