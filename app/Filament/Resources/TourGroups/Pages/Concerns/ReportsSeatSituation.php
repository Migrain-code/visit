<?php

namespace App\Filament\Resources\TourGroups\Pages\Concerns;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\TourGroup;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Grup kaydedildikten sonra koltuk durumunu kullanıcıya bildirir: kapasite aşıldı mı,
 * grup büyüdüğü için araçtan çıkarıldı mı, dağıtım bekliyor mu.
 */
trait ReportsSeatSituation
{
    protected function reportSeatSituation(TourGroup $group, bool $hadVehicle = false): void
    {
        $group->refresh();
        $departure = $group->departure()->withSeatStats()->first();

        if (! $departure || ! $group->status->holdsSeats()) {
            return;
        }

        $canAllocate = auth()->user()?->can('allocate', $departure) ?? false;
        $actions = $canAllocate
            ? [Action::make('board')->label('Araç dağılımını aç')->url(TourDepartureResource::getUrl('allocation', ['record' => $departure]))]
            : [];

        if ($departure->sale_limit !== null && $departure->seats_taken > $departure->sale_limit) {
            Notification::make()
                ->title('Tur kapasitesi aşıldı')
                ->body("Kayıtlı yolcu: {$departure->seats_taken}, kapasite: {$departure->sale_limit}. Tura araç eklenmeli ya da kontenjan artırılmalı.")
                ->danger()->persistent()->actions($actions)->send();

            return;
        }

        if ($hadVehicle && ! $group->departure_vehicle_id) {
            Notification::make()
                ->title('Grup araçtan çıkarıldı')
                ->body('Grup bulunduğu araca artık bütün hâlinde sığmıyor. Bölünmemesi için yerleşmeyi bekleyenlere alındı; yeniden dağıtılmalı.')
                ->warning()->persistent()->actions($actions)->send();

            return;
        }

        if (! $group->departure_vehicle_id && $departure->capacity > 0 && $canAllocate) {
            Notification::make()
                ->title('Grup henüz bir araca yerleşmedi')
                ->body('Araç dağılımından "Bekleyenleri yerleştir" ile yerleştirebilirsiniz.')
                ->info()->actions($actions)->send();
        }
    }
}
