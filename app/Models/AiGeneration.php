<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Her AI çağrısının denetim kaydı (spec §3.3).
 */
class AiGeneration extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_RUNNING => 'Çalışıyor',
        self::STATUS_SUCCESS => 'Başarılı',
        self::STATUS_FAILED => 'Başarısız',
    ];

    public const OPERATIONS = [
        'blog.topics' => 'Blog konusu üretimi',
        'blog.article' => 'Blog yazısı üretimi',
        'seo.meta' => 'Meta üretimi',
        'seo.faq' => 'SSS üretimi',
        'seo.report' => 'Analitik rapor yorumu',
    ];

    protected $fillable = [
        'operation', 'content_type', 'content_id', 'batch_key', 'model',
        'status', 'input', 'output', 'error', 'duration_ms',
    ];

    protected function casts(): array
    {
        return ['input' => 'array'];
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function getOperationLabelAttribute(): string
    {
        return self::OPERATIONS[$this->operation] ?? $this->operation;
    }
}
