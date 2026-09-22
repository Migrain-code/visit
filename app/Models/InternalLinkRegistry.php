<?php

namespace App\Models;

use App\Support\PathNormalizer;
use Illuminate\Database\Eloquent\Model;

class InternalLinkRegistry extends Model
{
    protected $table = 'internal_link_registry';

    protected $fillable = ['target_url', 'owner_keyword', 'anchor_pool', 'status'];

    protected function casts(): array
    {
        return ['anchor_pool' => 'array', 'status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            $row->target_url = PathNormalizer::normalize($row->target_url);
            $row->target_hash = PathNormalizer::hash($row->target_url);
        });
    }
}
