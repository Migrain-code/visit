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
 * TUR: belirli bir tarihte yapılan tek gezi ("Batum · 12 Nisan · 1.250 TL").
 *
 * Katalog/sefer ayrımı kaldırıldı: aynı rotanın ikinci tarihi ayrı bir kayıttır.
 * Araçlar, gruplar, komisyonlar ve kasa hareketleri buna bağlanır. Sınıf adı
 * geçmişten kalır (tablo: tour_departures); panelde ve sitede adı "Tur"dur.
 */
class TourDeparture extends Model
{
    protected $fillable = [
        'title', 'image', 'image_alt', 'badge', 'short_description', 'description',
        'code', 'starts_at', 'ends_on', 'meeting_point', 'price', 'quota',
        'guide_id', 'status', 'is_public', 'sort_order', 'notes', 'allocated_at', 'created_by',
    ];

    protected $attributes = [
        'status' => 'open',
        'is_public' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_on' => 'date',
            'price' => 'decimal:2',
            'quota' => 'integer',
            'sort_order' => 'integer',
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
            $departure->title = trim((string) $departure->title);

            // Dönüş tarihi girilmediyse günübirliktir.
            if (blank($departure->ends_on) && $departure->starts_at) {
                $departure->ends_on = $departure->starts_at->copy()->startOfDay();
            }
        });
    }

    /** "BAT-120426", çakışırsa "BAT-120426-2". Yalnız iç kullanım (yolcu listesi başlığı). */
    public static function generateCode(self $departure): string
    {
        $prefix = Str::upper(Str::substr(Str::slug((string) $departure->title, ''), 0, 3)) ?: 'TUR';
        $base = $prefix.'-'.($departure->starts_at?->format('dmy') ?? now()->format('dmy'));
        $code = $base;
        $suffix = 2;

        while (static::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    // ---------- İlişkiler ----------

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

    public function commissions(): HasMany
    {
        return $this->hasMany(TourCommission::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TourLedgerEntry::class)->orderBy('entry_date')->orderBy('id');
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

    /**
     * Yetkisi olmayan hesap (rehber) yalnız rehberi olduğu turları görür: turun
     * kendisinde ya da araçlarından birinde rehber olarak yazılıysa.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isGuideOnly()) {
            return $query->where(fn (Builder $q) => $q
                ->where('guide_id', $user->getKey())
                ->orWhereHas('vehicles', fn (Builder $v) => $v->where('guide_id', $user->getKey())));
        }

        return $query;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }

    /** Web sitesinde gösterilen turlar. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('is_public', true)
            ->where('status', DepartureStatus::Open->value)
            ->where('starts_at', '>', now());
    }

    /** Ana sayfadaki sıra: panelden sürüklenen sıra, eşitse tarih. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('starts_at');
    }

    /** Liste ekranları için koltuk özetini TEK sorguda getirir. */
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

    /** Kasa için toplamlar: araç ücreti, komisyon, ekstra gelir/gider — tek sorguda. */
    public function scopeWithFinanceStats(Builder $query): Builder
    {
        return $query->withSeatStats()->addSelect([
            'vehicle_cost_sum' => DepartureVehicle::query()
                ->selectRaw('coalesce(sum(cost), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id'),
            'commission_sum' => TourCommission::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id'),
            'extra_income_sum' => TourLedgerEntry::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id')
                ->where('type', TourLedgerEntry::TYPE_INCOME),
            'extra_expense_sum' => TourLedgerEntry::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('tour_departure_id', 'tour_departures.id')
                ->where('type', TourLedgerEntry::TYPE_EXPENSE),
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

    /** Araçlardaki boş koltuk (kontenjandan bağımsız). Araç yoksa null. */
    public function getEmptySeatsAttribute(): ?int
    {
        return $this->capacity > 0 ? max(0, $this->capacity - $this->seats_taken) : null;
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

    // ---------- Kasa ----------

    private function financeSum(string $key, callable $fallback): float
    {
        return array_key_exists($key, $this->attributes)
            ? (float) $this->attributes[$key]
            : (float) $fallback();
    }

    public function getVehicleCostAttribute(): float
    {
        return $this->financeSum('vehicle_cost_sum', fn () => $this->vehicles()->sum('cost'));
    }

    public function getCommissionTotalAttribute(): float
    {
        return $this->financeSum('commission_sum', fn () => $this->commissions()->sum('amount'));
    }

    public function getExtraIncomeAttribute(): float
    {
        return $this->financeSum('extra_income_sum', fn () => $this->ledgerEntries()->where('type', TourLedgerEntry::TYPE_INCOME)->sum('amount'));
    }

    public function getExtraExpenseAttribute(): float
    {
        return $this->financeSum('extra_expense_sum', fn () => $this->ledgerEntries()->where('type', TourLedgerEntry::TYPE_EXPENSE)->sum('amount'));
    }

    /** Yolcu geliri: kayıtlı yolcu × kişi başı fiyat. */
    public function getPassengerRevenueAttribute(): float
    {
        return $this->seats_taken * (float) ($this->price ?? 0);
    }

    public function getTotalIncomeAttribute(): float
    {
        return $this->passenger_revenue + $this->extra_income;
    }

    public function getTotalExpenseAttribute(): float
    {
        return $this->vehicle_cost + $this->commission_total + $this->extra_expense;
    }

    /** Turdan kasaya kalan. */
    public function getNetAttribute(): float
    {
        return $this->total_income - $this->total_expense;
    }

    // ---------- Görünüm yardımcıları ----------

    public function getEffectivePriceAttribute(): ?float
    {
        return $this->price !== null ? (float) $this->price : null;
    }

    public function getPriceLabelAttribute(): ?string
    {
        return $this->effective_price !== null ? money_label($this->effective_price) : null;
    }

    public function getImageUrlAttribute(): string
    {
        return media_url($this->image, asset('images/placeholder.svg'));
    }

    /** "Batum Turu · 12.04.2026" */
    public function getLabelAttribute(): string
    {
        return trim(($this->title ?: 'Tur').' · '.$this->starts_at?->format('d.m.Y'));
    }

    /** "12 Nisan Cts" — sitedeki kart. */
    public function getShortDateLabelAttribute(): string
    {
        return (string) $this->starts_at?->translatedFormat('j F D');
    }

    /** "12 Nisan 2026 Cumartesi" */
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

    public function getIsPastAttribute(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }
}
