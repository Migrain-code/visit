<?php

namespace App\Filament\Resources\ReservationRequests\Pages;

use App\Filament\Resources\ReservationRequests\ReservationRequestResource;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\ReservationRequest;
use App\Models\TourGroup;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReservationRequest extends ViewRecord
{
    protected static string $resource = ReservationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convert')
                ->label('Gruba dönüştür')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (ReservationRequest $record) => $record->group === null
                    && (auth()->user()?->can('create', TourGroup::class) ?? false))
                ->url(fn (ReservationRequest $record) => TourGroupResource::getUrl('create', ['request' => $record->getKey()])),
            Action::make('whatsapp')
                ->label('WhatsApp\'tan yaz')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (ReservationRequest $record) => 'https://wa.me/'.ltrim(phone_digits($record->phone), '+'))
                ->openUrlInNewTab(),
            Action::make('call')
                ->label('Ara')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->url(fn (ReservationRequest $record) => phone_href($record->phone)),
            EditAction::make(),
        ];
    }
}
