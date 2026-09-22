<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalogdaki tur: web sitesinde yayınlanan tanıtım sayfası.
 *
 * Belirli bir tarihteki sefer ve yolcuları TourDeparture'dadır.
 */
class Tour extends Model
{
    protected $fillable = [
        'tour_category_id', 'title', 'slug', 'image', 'image_alt', 'gallery',
        'short_description', 'description', 'duration_days', 'duration_nights',
        'price', 'old_price', 'currency', 'price_note',
        'departure_point', 'destinations', 'transport', 'accommodation',
        'highlights', 'included', 'excluded', 'itinerary', 'faqs',
        'meta_title', 'meta_description', 'is_featured', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'highlights' => 'array',
            'included' => 'array',
            'excluded' => 'array',
            'itinerary' => 'array',
            'faqs' => 'array',
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'tour_category_id');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class);
    }

    /** Sitede gösterilecek seferler: yayında, kayda açık, tarihi geçmemiş. */
    public function upcomingDepartures(): HasMany
    {
        return $this->departures()->bookable()->orderBy('starts_at');
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Sitede gösterilecek kategori: YALNIZ yayındaki kategori yüklenir.
     *
     * Yayından kaldırılan kategorinin sayfası 404 döner; tur kartı, ekmek kırıntısı ve
     * şema o sayfaya bağlantı vermemelidir. Tur ise yayında kalmaya devam eder.
     */
    public function scopeWithPublicCategory(Builder $query): Builder
    {
        return $query->with(['category' => fn ($q) => $q->where('is_active', true)->select(['id', 'name', 'slug'])]);
    }

    public function getUrlAttribute(): string
    {
        return url('/'.$this->slug);
    }

    public function getImageUrlAttribute(): string
    {
        return media_url($this->image, asset('images/placeholder.svg'));
    }

    /** "2 Gece 3 Gün" / "Günübirlik" */
    public function getDurationLabelAttribute(): string
    {
        $days = max(1, (int) $this->duration_days);
        $nights = (int) $this->duration_nights;

        if ($nights === 0 && $days === 1) {
            return 'Günübirlik';
        }

        return $nights > 0 ? "{$nights} Gece {$days} Gün" : "{$days} Gün";
    }

    public function getIsDayTripAttribute(): bool
    {
        return (int) $this->duration_nights === 0 && (int) $this->duration_days <= 1;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->price !== null && $this->old_price !== null && (float) $this->old_price > (float) $this->price;
    }

    public function getDiscountPercentAttribute(): ?int
    {
        return $this->has_discount
            ? (int) round((1 - (float) $this->price / (float) $this->old_price) * 100)
            : null;
    }

    public function getPriceLabelAttribute(): ?string
    {
        return $this->price !== null ? money_label((float) $this->price, $this->currency) : null;
    }

    public function getOldPriceLabelAttribute(): ?string
    {
        return $this->has_discount ? money_label((float) $this->old_price, $this->currency) : null;
    }
}
