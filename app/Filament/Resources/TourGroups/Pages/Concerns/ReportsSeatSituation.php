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

        $board = Action::make('board')
            ->label('Araç dağılımını aç')
            ->url(TourDepartureResource::getUrl('allocation', ['record' => $departure]));

        if ($departure->sale_limit !== null && $departure->seats_taken > $departure->sale_limit) {
            Notification::make()
                ->title('Sefer kapasitesi aşıldı')
                ->body("Kayıtlı yolcu: {$departure->seats_taken}, kapasite: {$departure->sale_limit}. Sefere araç ekleyin ya da kontenjanı artırın.")
                ->danger()->persistent()->actions([$board])->send();

            return;
        }

        if ($hadVehicle && ! $group->departure_vehicle_id) {
            Notification::make()
                ->title('Grup araçtan çıkarıldı')
                ->body('Grup bulunduğu araca artık bütün hâlinde sığmıyor. Bölünmemesi için yerleşmeyi bekleyenlere alındı; yeniden dağıtın.')
                ->warning()->persistent()->actions([$board])->send();

            return;
        }

        if (! $group->departure_vehicle_id && $departure->capacity > 0) {
            Notification::make()
                ->title('Grup henüz bir araca yerleşmedi')
                ->body('Araç dağılımından "Bekleyenleri yerleştir" ile yerleştirebilirsiniz.')
                ->info()->actions([$board])->send();
        }
    }
}
