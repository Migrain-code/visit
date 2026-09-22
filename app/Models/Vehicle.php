<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hazır araç listesi (filo): 19, 24, 50 koltuklu... Seferlere buradan araç atanır.
 */
class Vehicle extends Model
{
    protected $fillable = [
        'name', 'plate', 'seat_count', 'driver_name', 'driver_phone', 'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'seat_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DepartureVehicle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('seat_count')->orderBy('name');
    }

    /** "Sprinter · 19 koltuk · 59 ABC 123" */
    public function getLabelAttribute(): string
    {
        return collect([$this->name, $this->seat_count.' koltuk', $this->plate])->filter()->implode(' · ');
    }
}
