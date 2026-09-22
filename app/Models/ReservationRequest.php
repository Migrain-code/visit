<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Web sitesinden gelen rezervasyon talebi.
 *
 * Talep bir ÖN KAYITTIR: koltuk tutmaz. Personel müşteriyi arar, yolcu bilgilerini
 * alır ve talebi bir gruba dönüştürür; koltuğu tutan gruptur.
 */
class ReservationRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NEW => 'Yeni',
        self::STATUS_CONTACTED => 'İletişime Geçildi',
        self::STATUS_RESERVED => 'Kayda Dönüştü',
        self::STATUS_CANCELLED => 'İptal / Olumsuz',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW => 'warning',
        self::STATUS_CONTACTED => 'info',
        self::STATUS_RESERVED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'tour_id', 'tour_departure_id', 'province_id', 'district_id',
        'people_count', 'preferred_date', 'message', 'kvkk_accepted', 'status', 'admin_notes',
        'source', 'page_url', 'ip', 'user_agent',
        'assigned_to', 'assigned_by', 'assigned_at', 'assignment_note',
    ];

    protected function casts(): array
    {
        return [
            'people_count' => 'integer',
            'preferred_date' => 'date',
            'kvkk_accepted' => 'boolean',
            'assigned_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** Talebi takip eden personel. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Bu talepten açılan grup kaydı. */
    public function group(): HasOne
    {
        return $this->hasOne(TourGroup::class);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function assignTo(User $staff, User $by, ?string $note = null): void
    {
        $this->forceFill([
            'assigned_to' => $staff->getKey(),
            'assigned_by' => $by->getKey(),
            'assigned_at' => now(),
            'assignment_note' => $note,
            // Atama yapıldıysa talep artık "yeni" değildir.
            'status' => $this->status === self::STATUS_NEW ? self::STATUS_CONTACTED : $this->status,
        ])->save();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getLocationLabelAttribute(): string
    {
        return collect([$this->district?->name, $this->province?->name])->filter()->implode(' / ');
    }
}
