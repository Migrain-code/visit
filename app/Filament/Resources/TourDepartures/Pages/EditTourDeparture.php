<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTourDeparture extends EditRecord
{
    protected static string $resource = TourDepartureResource::class;

    protected static ?string $navigationLabel = 'Düzenle';

    /** Üst sekmelerde yalnız düzenleme yetkisi olana görünür (rehber ve kayıt personeli görmez). */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record ? TourDepartureResource::canEdit($record) : parent::canAccess($parameters);
    }

    /** Araç ve grup listeleri "Özet" sayfasındadır; burada yalnız sefer bilgisi düzenlenir. */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->tooltip('Yolcu kaydı olan sefer silinemez.'),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return TourDepartureResource::getUrl('view', ['record' => $this->record]);
    }
}
