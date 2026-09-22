<?php

namespace App\Filament\Resources\Passengers\Pages;

use App\Filament\Resources\Passengers\PassengerResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePassengers extends ManageRecords
{
    protected static string $resource = PassengerResource::class;
}
