<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir turda bir personele ödenen sabit komisyon.
 *
 * Tur başına personel başına TEK satır (benzersiz). Personel kendi ekranında
 * bunların toplamını "kazancım" olarak görür; kasa raporunda gider kalemidir.
 */
class TourCommission extends Model
{
    protected $fillable = ['tour_departure_id', 'user_id', 'amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $commission) {
            if (blank($commission->created_by) && auth()->check()) {
                $commission->created_by = auth()->id();
            }
        });
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
