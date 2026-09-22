<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandRun extends Model
{
    use Prunable;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const STATUS_LABELS = [
        self::QUEUED => 'Sırada',
        self::RUNNING => 'Çalışıyor',
        self::SUCCEEDED => 'Başarılı',
        self::FAILED => 'Başarısız',
    ];

    public const STATUS_COLORS = [
        self::QUEUED => 'gray',
        self::RUNNING => 'info',
        self::SUCCEEDED => 'success',
        self::FAILED => 'danger',
    ];

    /** Kayıtlar 30 gün tutulur. */
    private const KEEP_DAYS = 30;

    protected $fillable = [
        'command_key', 'command_line', 'mode', 'status', 'exit_code', 'output',
        'user_id', 'started_at', 'finished_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::QUEUED, self::RUNNING]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    public function durationLabel(): ?string
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return $this->duration_ms < 1000
            ? $this->duration_ms.' ms'
            : number_format($this->duration_ms / 1000, 1, ',', '.').' sn';
    }
}
