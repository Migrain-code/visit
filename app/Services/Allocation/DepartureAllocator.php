<?php

namespace App\Services\Allocation;

use App\Models\DepartureVehicle;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bir seferin gruplarını o sefere atanmış araçlara dağıtır ve sonucu KAYDEDER.
 *
 * Kurallar VehicleAllocator'dadır (grup bölünmez, kapasite aşılmaz). Burası yalnız
 * veritabanıyla o saf algoritma arasındaki köprüdür.
 */
class DepartureAllocator
{
    public function __construct(private VehicleAllocator $allocator) {}

    /**
     * @param  bool  $keepExisting  true: yerleşmiş gruplara dokunma, yalnız açıkta kalanları yerleştir.
     *                              false: baştan dağıt (elle sabitlenenler hariç).
     */
    public function allocate(TourDeparture $departure, bool $keepExisting = false): AllocationReport
    {
        return DB::transaction(function () use ($departure, $keepExisting) {
            $vehicles = $departure->vehicles()->get();
            $groups = $departure->seatHoldingGroups()->orderBy('id')->get();

            $result = $this->allocator->allocate(
                $vehicles->map(fn (DepartureVehicle $v) => ['id' => $v->getKey(), 'capacity' => $v->usable_seats])->all(),
                $groups->map(fn (TourGroup $g) => [
                    'id' => $g->getKey(),
                    'size' => (int) $g->passenger_count,
                    'fixed' => ($g->departure_vehicle_id && ($g->is_pinned || $keepExisting)) ? $g->departure_vehicle_id : null,
                ])->all(),
            );

            $moved = 0;

            foreach ($groups as $group) {
                $target = $result->assignments[$group->getKey()] ?? null;
                $target = $target === null ? null : (int) $target;
                $current = $group->departure_vehicle_id === null ? null : (int) $group->departure_vehicle_id;

                if ($current !== $target) {
                    // Olay tetiklenmesin: araç uyumu zaten algoritmada doğrulandı.
                    $group->forceFill(['departure_vehicle_id' => $target])->saveQuietly();
                    $moved++;
                }
            }

            $departure->forceFill(['allocated_at' => now()])->saveQuietly();

            $unplaced = $groups->filter(fn (TourGroup $g) => isset($result->unplaced[$g->getKey()]))->values();
            $placed = $groups->filter(fn (TourGroup $g) => ($result->assignments[$g->getKey()] ?? null) !== null);
            $largest = (int) $vehicles->max(fn (DepartureVehicle $v) => $v->usable_seats);

            $reasons = [];

            foreach ($unplaced as $group) {
                $reasons[$group->getKey()] = match ($result->unplaced[$group->getKey()]) {
                    AllocationResult::REASON_NO_VEHICLE => 'sefere henüz araç atanmadı',
                    AllocationResult::REASON_TOO_BIG => "grup {$group->passenger_count} kişi; en büyük araçta {$largest} yolcu koltuğu var",
                    default => 'kalan boş koltuklar bu grubu bölmeden almaya yetmiyor',
                };
            }

            $names = $vehicles->keyBy(fn (DepartureVehicle $v) => $v->getKey());

            return new AllocationReport(
                placedGroups: $placed->count(),
                placedPassengers: (int) $placed->sum('passenger_count'),
                movedGroups: $moved,
                unplaced: $unplaced,
                reasons: $reasons,
                emptyVehicles: $placed->isEmpty() ? [] : array_map(fn ($id) => $names[$id]->label, $result->emptyVehicles()),
                overloaded: array_map(
                    fn ($id, $excess) => "{$names[$id]->label}: elle sabitlenen gruplar aracı {$excess} koltuk taşırıyor. Sabitlemeyi kaldırın ya da bir grubu taşıyın.",
                    array_keys($result->overloaded),
                    $result->overloaded,
                ),
                suggestion: $this->suggestVehicle($unplaced->pluck('passenger_count')->map(fn ($n) => (int) $n)->all()),
                exact: $result->exact,
            );
        });
    }

    /** Tüm araç atamalarını kaldırır. Elle sabitlenenler de dahil. */
    public function reset(TourDeparture $departure): int
    {
        $count = $departure->groups()->whereNotNull('departure_vehicle_id')->count();

        $departure->groups()->update(['departure_vehicle_id' => null, 'is_pinned' => false]);
        $departure->forceFill(['allocated_at' => null])->saveQuietly();

        return $count;
    }

    /**
     * Grubu elle bir araca taşır (ya da araçtan çıkarır) ve oraya SABİTLER.
     *
     * @throws RuntimeException grup bütün hâlinde sığmıyorsa — asla bölünmez, asla taşırmaz
     */
    public function move(TourGroup $group, ?DepartureVehicle $vehicle): void
    {
        if ($vehicle === null) {
            $group->forceFill(['departure_vehicle_id' => null, 'is_pinned' => false])->saveQuietly();

            return;
        }

        if ((int) $vehicle->tour_departure_id !== (int) $group->tour_departure_id) {
            throw new RuntimeException('Bu araç grubun seferine ait değil.');
        }

        if (! $group->status->holdsSeats()) {
            throw new RuntimeException('İptal edilmiş grup araca yerleştirilemez.');
        }

        $occupied = (int) TourGroup::query()
            ->where('departure_vehicle_id', $vehicle->getKey())
            ->whereKeyNot($group->getKey())
            ->seatHolding()
            ->sum('passenger_count');

        $free = $vehicle->usable_seats - $occupied;

        if ($group->passenger_count > $free) {
            throw new RuntimeException(
                "{$vehicle->name} aracında {$free} boş koltuk var; {$group->passenger_count} kişilik grup bölünmeden sığmıyor."
            );
        }

        $group->forceFill(['departure_vehicle_id' => $vehicle->getKey(), 'is_pinned' => true])->saveQuietly();
    }

    /**
     * Açıkta kalan gruplar için filodan ek araç önerir.
     *
     * @param  array<int, int>  $sizes  yerleşmeyen grupların büyüklükleri
     */
    public function suggestVehicle(array $sizes): ?string
    {
        $sizes = array_values(array_filter($sizes));

        if ($sizes === []) {
            return null;
        }

        $needed = array_sum($sizes);
        $fleet = Vehicle::query()->active()->orderBy('seat_count')->get();

        if ($fleet->isEmpty()) {
            return null;
        }

        // Hepsini tek başına alan en küçük araç.
        if ($single = $fleet->first(fn (Vehicle $v) => $v->seat_count >= $needed)) {
            return "Öneri: açıkta kalan {$needed} yolcu için sefere \"{$single->label}\" ekleyin.";
        }

        $largestGroup = max($sizes);
        $largest = $fleet->last();

        if ($largest->seat_count < $largestGroup) {
            return "Filodaki en büyük araç {$largest->seat_count} koltuklu; {$largestGroup} kişilik grup hiçbirine bölünmeden sığmaz. Daha büyük bir araç tanımlayın.";
        }

        $count = (int) ceil($needed / $largest->seat_count);

        return "Öneri: açıkta kalan {$needed} yolcu için en az {$count} adet \"{$largest->label}\" gerekir.";
    }
}
