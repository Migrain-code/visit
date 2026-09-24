<?php

namespace App\Filament\Resources\VehicleHistory\Pages;

use App\Filament\Resources\VehicleHistory\VehicleHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleHistory extends ListRecords
{
    protected static string $resource = VehicleHistoryResource::class;

    protected static ?string $title = 'Araç Geçmişi';

    public function getSubheading(): ?string
    {
        return 'Hangi araç hangi tura gitti, kaç yolcu taşıdı. Plaka ve şoför tur günündeki hâliyle saklanır.';
    }
}
