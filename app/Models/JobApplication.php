<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sitedeki "İş Başvurusu" formundan gelen başvuru.
 */
class JobApplication extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_INTERVIEW = 'interview';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW => 'Yeni',
        self::STATUS_REVIEWING => 'İnceleniyor',
        self::STATUS_INTERVIEW => 'Görüşme',
        self::STATUS_ACCEPTED => 'Kabul',
        self::STATUS_REJECTED => 'Olumsuz',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW => 'warning',
        self::STATUS_REVIEWING => 'info',
        self::STATUS_INTERVIEW => 'primary',
        self::STATUS_ACCEPTED => 'success',
        self::STATUS_REJECTED => 'danger',
    ];

    public const POSITIONS = [
        'Rehber',
        'Şoför',
        'Organizasyon / Operasyon',
        'Sosyal Medya',
        'Fotoğraf / Video',
        'Diğer',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'birth_date', 'position', 'message', 'cv_path',
        'status', 'admin_notes', 'kvkk_accepted', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'kvkk_accepted' => 'boolean',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getCvUrlAttribute(): ?string
    {
        return $this->cv_path ? route('admin.job-application.cv', $this) : null;
    }
}
