<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReservationRequests\ReservationRequestResource;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\ReservationRequest;
use App\Models\TourCommission;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Panonun özet kartları. Rehber için sayılar KENDİ turları üzerinden hesaplanır.
 */
class OperationStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** İlk boyamada hazır olsun: telefonda ikinci bir istek beklenmez. */
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user = auth()->user();

        $departures = fn () => TourDeparture::query()->visibleTo($user)->upcoming();
        $groups = fn () => TourGroup::query()->visibleTo($user)->seatHolding()
            ->whereHas('departure', fn ($q) => $q->upcoming());

        $upcoming = $departures()->count();
        $next = $departures()->withSeatStats()->orderBy('starts_at')->first();
        $passengers = (int) $groups()->sum('passenger_count');
        $waiting = (int) $groups()->whereNull('departure_vehicle_id')->sum('passenger_count');

        $stats = [
            Stat::make('Yaklaşan tur', $upcoming)
                ->description($next ? 'Sıradaki: '.$next->starts_at->format('d.m').' · '.$next->title : 'Planlı tur yok')
                ->color('info')
                ->url(TourDepartureResource::getUrl('index')),
            Stat::make('Kayıtlı yolcu', $passengers)
                ->description('Yaklaşan turlarda, '.$groups()->count().' grup')
                ->color('success'),
            Stat::make('Sıradaki turda boş koltuk', $next?->empty_seats ?? '—')
                ->description($next ? ($next->capacity > 0 ? $next->capacity.' koltuk · '.$next->seats_taken.' dolu' : 'Araç atanmadı') : 'Planlı tur yok')
                ->color(match (true) {
                    $next === null || $next->empty_seats === null => 'gray',
                    $next->empty_seats === 0 => 'danger',
                    $next->empty_seats <= 5 => 'warning',
                    default => 'success',
                }),
        ];

        if ($user?->managesOperations()) {
            $stats[] = Stat::make('Araca yerleşmemiş', $waiting)
                ->description($waiting > 0 ? 'Yolcu dağıtım bekliyor' : 'Herkes bir araca yerleşti')
                ->color($waiting > 0 ? 'warning' : 'success');
        }

        if ($user?->managesRequests()) {
            $new = ReservationRequest::query()->where('status', ReservationRequest::STATUS_NEW)->count();

            $stats[] = Stat::make('Yeni iletişim talebi', $new)
                ->description('Henüz iletişime geçilmedi')
                ->color($new > 0 ? 'warning' : 'success')
                ->url(ReservationRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'new']]]));
        }

        if ($user && ! $user->viewsReports()) {
            $earned = (float) TourCommission::query()->where('user_id', $user->getKey())->sum('amount');

            $stats[] = Stat::make('Kazancım', money_label($earned))
                ->description('Yazılan komisyonların toplamı')
                ->color('success');
        }

        return $stats;
    }
}
