<?php

namespace App\Models;

use App\Enums\GroupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir sefere atanmış araç.
 *
 * Koltuk sayısı ve şoför bilgisi filodan KOPYALANIR; filodaki kayıt sonradan değişse
 * de geçmiş seferin yolcu listesi bozulmaz.
 */
class DepartureVehicle extends Model
{
    protected $fillable = [
        'tour_departure_id', 'vehicle_id', 'name', 'plate', 'seat_count', 'reserved_seats',
        'driver_name', 'driver_phone', 'notes', 'sort_order',
    ];

    protected $attributes = [
        'reserved_seats' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seat_count' => 'integer',
            'reserved_seats' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vehicle) {
            // Yalnız filodan araç seçildiyse boş alanlar filodan doldurulur.
            if ($vehicle->vehicle_id && ($source = Vehicle::find($vehicle->vehicle_id))) {
                $vehicle->name = $vehicle->name ?: $source->name;
                $vehicle->seat_count = $vehicle->seat_count ?: $source->seat_count;
                $vehicle->plate = $vehicle->plate ?: $source->plate;
                $vehicle->driver_name = $vehicle->driver_name ?: $source->driver_name;
                $vehicle->driver_phone = $vehicle->driver_phone ?: $source->driver_phone;
            }

            if (blank($vehicle->sort_order)) {
                $vehicle->sort_order = (int) static::query()
                    ->where('tour_departure_id', $vehicle->tour_departure_id)
                    ->max('sort_order') + 1;
            }
        });

        // Koltuk azaltıldıysa ve araç taşıyorsa içindeki gruplar boşa çıkarılır.
        // Taşmış bir araç listesiyle yola çıkmaktansa yeniden dağıtmak güvenlidir.
        static::updated(function (self $vehicle) {
            if ($vehicle->wasChanged(['seat_count', 'reserved_seats']) && $vehicle->occupied_seats > $vehicle->usable_seats) {
                $vehicle->releaseGroups();
            }
        });

        static::deleting(fn (self $vehicle) => $vehicle->releaseGroups());
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TourGroup::class)->orderBy('id');
    }

    /** Yolcuya açık koltuk: rehber/görevli için ayrılanlar düşülür. */
    public function getUsableSeatsAttribute(): int
    {
        return max(0, (int) $this->seat_count - (int) $this->reserved_seats);
    }

    /** Araçtaki yolcu sayısı. */
    public function getOccupiedSeatsAttribute(): int
    {
        if ($this->relationLoaded('groups')) {
            return (int) $this->groups
                ->filter(fn (TourGroup $g) => $g->status !== GroupStatus::Cancelled)
                ->sum('passenger_count');
        }

        return (int) $this->groups()->where('status', '!=', GroupStatus::Cancelled->value)->sum('passenger_count');
    }

    public function getFreeSeatsAttribute(): int
    {
        return max(0, $this->usable_seats - $this->occupied_seats);
    }

    public function getLabelAttribute(): string
    {
        return collect([$this->name, $this->seat_count.' koltuk', $this->plate])->filter()->implode(' · ');
    }

    /** Araçtaki tüm grupları boşa çıkarır (sabitlemeyi de kaldırır). */
    public function releaseGroups(): int
    {
        return TourGroup::query()
            ->where('departure_vehicle_id', $this->getKey())
            ->update(['departure_vehicle_id' => null, 'is_pinned' => false]);
    }
}
