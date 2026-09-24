<?php

namespace App\Models;

use App\Enums\GroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Birlikte seyahat eden yolcular: "Yolcu Ekle" ekranında tek seferde girilen liste.
 *
 * GRUP BÖLÜNMEZ. Araç ataması bu modeldedir (departure_vehicle_id); yolcuların ayrı
 * araç alanı yoktur. Bir grubun iki araca dağılması veri modelinde mümkün değildir.
 *
 * Ad verilmezse tur içinde sırayla "Grup 1", "Grup 2"... adı alır. İletişim kişisi
 * ilk yolcudur ve yolcu satırları değiştikçe kendiliğinden güncellenir.
 */
class TourGroup extends Model
{
    protected $fillable = [
        'tour_departure_id', 'code', 'name', 'contact_name', 'contact_phone', 'contact_email',
        'pickup_point', 'status', 'departure_vehicle_id', 'is_pinned',
        'total_price', 'paid_amount', 'notes', 'reservation_request_id', 'created_by',
    ];

    protected $attributes = [
        'status' => 'confirmed',
        'is_pinned' => false,
        'passenger_count' => 0,
        'paid_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupStatus::class,
            'is_pinned' => 'boolean',
            'passenger_count' => 'integer',
            'total_price' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $group) {
            // Kesin kod kayıttan sonra verilir (kimliğe dayanır); sütun boş geçilemediği için geçici değer.
            $group->code = $group->code ?: 'TMP-'.bin2hex(random_bytes(6));

            if (blank($group->name)) {
                $group->name = static::nextName($group->tour_departure_id);
            }

            // İletişim kişisi yolculardan türer; ilk yolcu henüz yazılmadıysa geçici değer.
            $group->contact_name = $group->contact_name ?: $group->name;
            $group->contact_phone = $group->contact_phone ?: '-';

            if (blank($group->created_by) && auth()->check()) {
                $group->created_by = auth()->id();
            }
        });

        static::created(function (self $group) {
            if (str_starts_with((string) $group->code, 'TMP-')) {
                $group->forceFill(['code' => 'G-'.str_pad((string) $group->getKey(), 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });

        static::saving(function (self $group) {
            // Formda boş bırakılan ödeme alanı "hiç ödeme alınmadı" demektir.
            $group->paid_amount ??= 0;

            // Başka tura taşınan ya da iptal edilen grup araçtaki yerini bırakır.
            $moved = $group->exists && $group->isDirty('tour_departure_id');
            $cancelled = $group->status === GroupStatus::Cancelled;

            if ($moved || $cancelled) {
                $group->departure_vehicle_id = null;
                $group->is_pinned = false;
            }
        });
    }

    /** Turdaki bir sonraki "Grup N" adı: en büyük numaranın bir fazlası. */
    public static function nextName(int|string|null $departureId): string
    {
        $max = static::query()
            ->where('tour_departure_id', $departureId)
            ->where('name', 'like', 'Grup %')
            ->pluck('name')
            ->map(fn (string $name) => (int) trim(substr($name, 5)))
            ->max();

        return 'Grup '.(((int) $max) + 1);
    }

    // ---------- İlişkiler ----------

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(DepartureVehicle::class, 'departure_vehicle_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    // ---------- Kapsamlar ----------

    public function scopeSeatHolding(Builder $query): Builder
    {
        return $query->where('status', '!=', GroupStatus::Cancelled->value);
    }

    /** Yetkisiz hesap (rehber) yalnız rehberi olduğu turların gruplarını görür. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isGuideOnly()) {
            return $query->whereHas('departure', fn (Builder $q) => $q->visibleTo($user));
        }

        return $query;
    }

    // ---------- Yolcu sayısı ve araç uyumu ----------

    /**
     * Yolcu sayısını ve iletişim kişisini satırlardan yeniden hesaplar.
     *
     * Grup büyüyüp bulunduğu araca sığmaz hâle geldiyse araçtan ÇIKARILIR: taşan bir
     * araçla ya da bölünmüş bir grupla yola çıkmaktansa grubun "yerleşmedi" görünmesi
     * ve yeniden dağıtılması doğrudur.
     *
     * @return bool grup araçtan çıkarıldıysa true
     */
    public function refreshPassengerCount(): bool
    {
        $passengers = $this->passengers()->get();
        $first = $passengers->first();

        $this->forceFill([
            'passenger_count' => $passengers->count(),
            'contact_name' => $first?->full_name ?: ($this->contact_name ?: $this->name),
            'contact_phone' => $first?->phone ?: ($passengers->firstWhere('phone', '!=', null)?->phone ?: ($this->contact_phone ?: '-')),
            'pickup_point' => $passengers->pluck('pickup_point')->filter()->unique()->implode(', ') ?: $this->pickup_point,
        ])->saveQuietly();

        return $this->releaseIfOverflowing();
    }

    public function releaseIfOverflowing(): bool
    {
        $vehicle = $this->departure_vehicle_id ? $this->vehicle()->first() : null;

        if (! $vehicle || $vehicle->occupied_seats <= $vehicle->usable_seats) {
            return false;
        }

        $this->forceFill(['departure_vehicle_id' => null, 'is_pinned' => false])->saveQuietly();

        return true;
    }

    // ---------- Görünüm yardımcıları ----------

    /** "Grup 3 (5 kişi)" */
    public function getDisplayNameAttribute(): string
    {
        return ($this->name ?: $this->contact_name).' ('.$this->passenger_count.' kişi)';
    }

    public function getBalanceAttribute(): ?float
    {
        return $this->total_price !== null ? (float) $this->total_price - (float) $this->paid_amount : null;
    }
}
