<?php

namespace App\Filament\Resources\AiGenerations\Pages;

use App\Filament\Resources\AiGenerations\AiGenerationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAiGenerations extends ManageRecords
{
    protected static string $resource = AiGenerationResource::class;

    public function getSubheading(): ?string
    {
        return 'Her AI çağrısının denetim kaydı: ne gönderildi, ne döndü, ne kadar sürdü. API anahtarı burada saklanmaz.';
    }
}
