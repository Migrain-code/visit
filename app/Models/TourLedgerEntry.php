<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tura bağlı ekstra gelir ya da gider (yemek, giriş ücreti, sponsorluk...).
 *
 * Yolcu geliri, araç ücreti ve komisyon başka yerlerden hesaplanır; burası
 * yalnız onların dışında kalan kalemler içindir.
 */
class TourLedgerEntry extends Model
{
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_INCOME => 'Gelir',
        self::TYPE_EXPENSE => 'Gider',
    ];

    protected $fillable = ['tour_departure_id', 'type', 'title', 'amount', 'entry_date', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if (blank($entry->created_by) && auth()->check()) {
                $entry->created_by = auth()->id();
            }

            $entry->entry_date ??= now()->toDateString();
        });
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getIsIncomeAttribute(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }
}
