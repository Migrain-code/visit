<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passenger extends Model
{
    protected $fillable = [
        'tour_group_id', 'first_name', 'last_name', 'is_foreign', 'tc_no', 'passport_no',
        'phone', 'pickup_point', 'age', 'gender', 'notes', 'sort_order',
    ];

    protected $attributes = [
        'is_foreign' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_foreign' => 'boolean',
            'age' => 'integer',
            'gender' => Gender::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $passenger) {
            $passenger->first_name = trim((string) $passenger->first_name);
            $passenger->last_name = trim((string) $passenger->last_name);
            $passenger->tc_no = filled($passenger->tc_no) ? preg_replace('/\D/', '', (string) $passenger->tc_no) : null;

            // Yabancı uyrukluda TC, TC sahibinde pasaport alanı boş kalır.
            if ($passenger->is_foreign) {
                $passenger->tc_no = null;
            } else {
                $passenger->passport_no = null;
            }
        });

        // Grubun büyüklüğü ve iletişim kişisi yolcu satırlarından türer; dağıtım bu sayıya güvenir.
        static::saved(fn (self $passenger) => $passenger->group?->refreshPassengerCount());
        static::deleted(fn (self $passenger) => $passenger->group?->refreshPassengerCount());
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TourGroup::class, 'tour_group_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Kimlik: TC ya da pasaport numarası. */
    public function getIdentityAttribute(): ?string
    {
        return $this->is_foreign ? $this->passport_no : $this->tc_no;
    }

    /** Liste ekranları için: "123******45". Tam numara yalnız kayıt detayında görünür. */
    public function getMaskedIdentityAttribute(): ?string
    {
        $value = (string) $this->identity;

        if (mb_strlen($value) < 6) {
            return $value !== '' ? $value : null;
        }

        return mb_substr($value, 0, 3).str_repeat('*', mb_strlen($value) - 5).mb_substr($value, -2);
    }
}
