<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoSearchQuery extends Model
{
    protected $fillable = [
        'dimension', 'query', 'path', 'clicks', 'impressions', 'ctr', 'position',
        'period_start', 'period_end', 'row_hash',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'ctr' => 'float',
            'position' => 'float',
        ];
    }

    public function scopeQueries(Builder $query): Builder
    {
        return $query->where('dimension', 'query');
    }

    public function scopePages(Builder $query): Builder
    {
        return $query->where('dimension', 'page');
    }

    public function scopeLatestPeriod(Builder $query): Builder
    {
        return $query->where('period_end', static::query()->max('period_end'));
    }

    /** Gösterimi yüksek, tıklaması düşük: meta tazelemeye aday (spec §4). */
    public function scopeLowCtr(Builder $query, int $minImpressions = 100, float $maxCtr = 0.02): Builder
    {
        return $query->where('impressions', '>=', $minImpressions)->where('ctr', '<=', $maxCtr);
    }
}
