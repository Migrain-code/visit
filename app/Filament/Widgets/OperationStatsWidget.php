<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReservationRequests\ReservationRequestResource;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->seesPassengers() ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        // Rehber için sayılar KENDİ seferleri üzerinden hesaplanır.
        $departures = fn () => TourDeparture::query()->visibleTo($user)->upcoming();
        $groups = fn () => TourGroup::query()->visibleTo($user)->seatHolding()
            ->whereHas('departure', fn ($q) => $q->upcoming());

        $upcoming = $departures()->count();
        $next = $departures()->orderBy('starts_at')->with('tour:id,title')->first();
        $passengers = (int) $groups()->sum('passenger_count');
        $waiting = (int) $groups()->whereNull('departure_vehicle_id')->sum('passenger_count');

        $stats = [
            Stat::make('Yaklaşan sefer', $upcoming)
                ->description($next ? 'Sıradaki: '.$next->starts_at->format('d.m').' · '.$next->tour?->title : 'Planlı sefer yok')
                ->color('info')
                ->url(TourDepartureResource::getUrl('index')),
            Stat::make('Kayıtlı yolcu', $passengers)
                ->description('Yaklaşan seferlerde, '.$groups()->count().' grup')
                ->color('success'),
            Stat::make('Araca yerleşmemiş', $waiting)
                ->description($waiting > 0 ? 'Yolcu dağıtım bekliyor' : 'Herkes bir araca yerleşti')
                ->color($waiting > 0 ? 'warning' : 'success'),
        ];

        if ($user?->registersGroups()) {
            $new = ReservationRequest::query()->where('status', ReservationRequest::STATUS_NEW)->count();

            $stats[] = Stat::make('Yeni rezervasyon talebi', $new)
                ->description('Henüz iletişime geçilmedi')
                ->color($new > 0 ? 'warning' : 'success')
                ->url(ReservationRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'new']]]));
        }

        return $stats;
    }
}
