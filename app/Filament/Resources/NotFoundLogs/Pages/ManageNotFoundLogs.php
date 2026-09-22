<?php

namespace App\Filament\Resources\NotFoundLogs\Pages;

use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageNotFoundLogs extends ManageRecords
{
    protected static string $resource = NotFoundLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Bot taramaları (wp-admin, .env, .php gibi) kaydedilmez; gerçek hatalar gömülmesin diye elenir.';
    }
}
