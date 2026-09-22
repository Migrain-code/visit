<?php

namespace App\Filament\Resources\TourGroups\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\TourGroups\Pages\Concerns\ReportsSeatSituation;
use App\Filament\Resources\TourGroups\TourGroupResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTourGroup extends EditRecord
{
    use ReportsSeatSituation;

    protected static string $resource = TourGroupResource::class;

    private bool $hadVehicle = false;

    protected function beforeSave(): void
    {
        $this->hadVehicle = filled($this->record->departure_vehicle_id);
    }

    protected function afterSave(): void
    {
        $this->reportSeatSituation($this->record, $this->hadVehicle);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('departure')
                ->label('Sefere git')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(fn () => TourDepartureResource::getUrl('view', ['record' => $this->record->tour_departure_id])),
            DeleteAction::make(),
        ];
    }
}
