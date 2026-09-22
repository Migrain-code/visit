<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Blog extends Model
{
    public const STATUS_DRAFT = 0;

    public const STATUS_PUBLISHED = 1;

    public const STATUSES = [
        self::STATUS_DRAFT => 'Taslak',
        self::STATUS_PUBLISHED => 'Yayında',
    ];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI = 'ai';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Elle yazıldı',
        self::SOURCE_AI => 'AI üretti',
    ];

    protected $fillable = [
        'blog_category_id', 'title', 'slug', 'excerpt', 'body_html', 'image', 'image_alt',
        'meta_title', 'meta_description', 'primary_keyword', 'faqs',
        'status', 'publish_at', 'source', 'ai_generation_id', 'merged_into_id', 'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'faqs' => 'array',
            'publish_at' => 'datetime',
            'merged_at' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class, 'owner_blog_id');
    }

    /** Yayında VE yayın zamanı gelmiş yazılar. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now());
    }

    public function path(): string
    {
        return '/blog/'.$this->slug;
    }

    public function getUrlAttribute(): string
    {
        return url($this->path());
    }

    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->image, asset('images/placeholder.svg'));
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && (! $this->publish_at || $this->publish_at->isPast());
    }

    public function getReadingMinutesAttribute(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body_html)) / 200));
    }

    public function getSummaryAttribute(): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->body_html)) ?? ''), 160);
    }
}
