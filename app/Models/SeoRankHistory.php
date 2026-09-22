<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoRankHistory extends Model
{
    protected $table = 'seo_rank_history';

    protected $fillable = [
        'keyword_id', 'data_date', 'position_avg', 'clicks', 'impressions',
        'ranking_url', 'target_match',
    ];

    protected function casts(): array
    {
        return [
            'data_date' => 'date',
            'position_avg' => 'float',
            'target_match' => 'boolean',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }
}
