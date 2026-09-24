<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Her personel için yapabileceklerini kutularla işaretleyin. Yetkisi olmayan personel yalnız rehberi olduğu turları ve kendi kazancını görür.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Personel ekle'),
        ];
    }
}
