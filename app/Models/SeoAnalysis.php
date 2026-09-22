<?php

namespace App\Models;

use App\Support\SeoConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoAnalysis extends Model
{
    public const INDEX_STATUSES = [
        'PASS' => 'İndekslendi',
        'PARTIAL' => 'Kısmen',
        'FAIL' => 'İndekslenmedi',
        'NEUTRAL' => 'Belirsiz',
        'UNKNOWN' => 'Bilinmiyor',
    ];

    protected $fillable = [
        'content_type', 'content_id', 'url', 'score', 'scores', 'issues', 'analyzed_at',
        'google_index_status', 'google_index_detail', 'index_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'issues' => 'array',
            'analyzed_at' => 'datetime',
            'index_checked_at' => 'datetime',
        ];
    }

    public function content(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'content_type', 'content_id');
    }

    public function scopeBelowTarget(Builder $query): Builder
    {
        return $query->where('score', '<', SeoConfig::int('score_target', 80, 1, 100));
    }

    public function getScoreColorAttribute(): string
    {
        return match (true) {
            $this->score >= 80 => 'success',
            $this->score >= 60 => 'warning',
            default => 'danger',
        };
    }

    public function getIssueCountAttribute(): int
    {
        return count($this->issues ?? []);
    }
}
