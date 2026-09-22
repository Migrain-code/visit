<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\Vehicle;
use Filament\Resources\Pages\CreateRecord;

class CreateTourDeparture extends CreateRecord
{
    protected static string $resource = TourDepartureResource::class;

    /** Formda seçilen araçlar sefere atanır (koltuk sayıları filodan kopyalanır). */
    protected function afterCreate(): void
    {
        $ids = array_filter((array) ($this->data['vehicle_ids'] ?? []));

        foreach (Vehicle::query()->whereKey($ids)->ordered()->get() as $vehicle) {
            $this->record->vehicles()->create(['vehicle_id' => $vehicle->getKey()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return TourDepartureResource::getUrl('view', ['record' => $this->record]);
    }
}
