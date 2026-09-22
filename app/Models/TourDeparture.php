<?php

namespace App\Models;

use App\Enums\DepartureStatus;
use App\Enums\GroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tur kaydı (sefer): bir turun belirli bir tarihte yapılan hâli.
 *
 * Araçlar ve gruplar buna bağlanır. "Ayder Yaylası Turu" katalogda tek kayıttır;
 * 25 Eylül ve 9 Ekim seferleri iki ayrı TourDeparture'dır.
 */
class TourDeparture extends Model
{
    protected $fillable = [
        'tour_id', 'code', 'starts_at', 'ends_on', 'meeting_point', 'price', 'quota',
        'guide_id', 'status', 'is_public', 'notes', 'allocated_at', 'created_by',
    ];

    protected $attributes = [
        'status' => 'open',
        'is_public' => true,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_on' => 'date',
            'price' => 'decimal:2',
            'quota' => 'integer',
            'status' => DepartureStatus::class,
            'is_public' => 'boolean',
            'allocated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $departure) {
            if (blank($departure->code)) {
                $departure->code = static::generateCode($departure);
            }

            if (blank($departure->created_by) && auth()->check()) {
                $departure->created_by = auth()->id();
            }
        });

        static::saving(function (self $departure) {
            // Bitiş tarihi girilmediyse turun süresinden hesaplanır.
            if (blank($departure->ends_on) && $departure->starts_at && $departure->tour) {
                $departure->ends_on = $departure->starts_at->copy()->startOfDay()
                    ->addDays(max(1, (int) $departure->tour->duration_days) - 1);
            }
        });
    }

    /** "KAP-250926", çakışırsa "KAP-250926-2". */
    public static function generateCode(self $departure): string
    {
        $tour = $departure->tour ?? Tour::find($departure->tour_id);
        $prefix = Str::upper(Str::substr(Str::slug((string) $tour?->title, ''), 0, 3)) ?: 'TUR';
        $base = $prefix.'-'.($departure->starts_at?->format('dmy') ?? now()->format('dmy'));
        $code = $base;
        $suffix = 2;

        while (static::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    // ---------- İlişkiler ----------

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(DepartureVehicle::class)->orderBy('sort_order')->orderBy('id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TourGroup::class)->orderBy('id');
    }

    /** Koltuk tutan gruplar: iptal edilenler hariç. */
    public function seatHoldingGroups(): HasMany
    {
        return $this->groups()->where('status', '!=', GroupStatus::Cancelled->value);
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guide_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------- Kapsamlar ----------

    /** Rehber yalnız kendi seferlerini görür. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isGuide()) {
            return $query->where('guide_id', $user->getKey());
        }

        return $query;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }

    /** Web sitesinde satışa açık seferler. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('is_public', true)
            ->where('status', DepartureStatus::Open->value)
            ->where('starts_at', '>', now());
    }

    /**
     * Liste ekranları için koltuk özetini TEK sorguda getirir (sefer başına iki ek
     * sorgu yerine alt sorgular).
     */
    public function scopeWithSeatStats(Builder $query): Builder
    {
        return $query->addSelect([
            'seats_total' => DepartureVehicle::query()
                ->selectRaw('coalesce(sum(cast(seat_count as signed) - cast(reserved_seats as signed)), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id'),
            'seats_taken_sum' => TourGroup::query()
                ->selectRaw('coalesce(sum(passenger_count), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id')
                ->where('status', '!=', GroupStatus::Cancelled->value),
            'waiting_sum' => TourGroup::query()
                ->selectRaw('coalesce(sum(passenger_count), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id')
                ->where('status', '!=', GroupStatus::Cancelled->value)
                ->whereNull('departure_vehicle_id'),
        ]);
    }

    // ---------- Koltuk hesabı ----------

    /** Atanan araçların kullanılabilir toplam koltuğu. */
    public function getCapacityAttribute(): int
    {
        if (array_key_exists('seats_total', $this->attributes)) {
            return max(0, (int) $this->attributes['seats_total']);
        }

        return (int) $this->vehicles->sum(fn (DepartureVehicle $v) => $v->usable_seats);
    }

    /** Kayıtlı yolcu sayısı (iptaller hariç). */
    public function getSeatsTakenAttribute(): int
    {
        if (array_key_exists('seats_taken_sum', $this->attributes)) {
            return (int) $this->attributes['seats_taken_sum'];
        }

        return (int) $this->seatHoldingGroups()->sum('passenger_count');
    }

    /** Satılabilecek en çok koltuk: kontenjan girildiyse o, yoksa araç kapasitesi. Bilinmiyorsa null. */
    public function getSaleLimitAttribute(): ?int
    {
        if ($this->quota !== null) {
            return (int) $this->quota;
        }

        return $this->capacity > 0 ? $this->capacity : null;
    }

    public function getSeatsLeftAttribute(): ?int
    {
        return $this->sale_limit === null ? null : max(0, $this->sale_limit - $this->seats_taken);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->seats_left === 0;
    }

    /** Araçlara henüz yerleşmemiş (koltuk tutan) yolcu sayısı. */
    public function getUnassignedPassengersAttribute(): int
    {
        if (array_key_exists('waiting_sum', $this->attributes)) {
            return (int) $this->attributes['waiting_sum'];
        }

        return (int) $this->seatHoldingGroups()->whereNull('departure_vehicle_id')->sum('passenger_count');
    }

    // ---------- Görünüm yardımcıları ----------

    public function getEffectivePriceAttribute(): ?float
    {
        $price = $this->price ?? $this->tour?->price;

        return $price !== null ? (float) $price : null;
    }

    public function getPriceLabelAttribute(): ?string
    {
        return $this->effective_price !== null
            ? money_label($this->effective_price, $this->tour?->currency ?? 'TRY')
            : null;
    }

    /** "Ayder Yaylası Turu · 25.09.2026" */
    public function getLabelAttribute(): string
    {
        return trim(($this->tour?->title ?? 'Tur').' · '.$this->starts_at?->format('d.m.Y'));
    }

    /** "25 Eylül 2026 Cuma" */
    public function getDateLabelAttribute(): string
    {
        return (string) $this->starts_at?->translatedFormat('j F Y l');
    }

    public function getDateRangeLabelAttribute(): string
    {
        if (! $this->starts_at) {
            return '';
        }

        if (! $this->ends_on || $this->ends_on->isSameDay($this->starts_at)) {
            return $this->starts_at->translatedFormat('j F Y');
        }

        return $this->starts_at->isSameMonth($this->ends_on)
            ? $this->starts_at->format('j').' - '.$this->ends_on->translatedFormat('j F Y')
            : $this->starts_at->translatedFormat('j F').' - '.$this->ends_on->translatedFormat('j F Y');
    }
}
