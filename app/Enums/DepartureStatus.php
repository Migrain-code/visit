<?php

namespace App\Enums;

/** Tur kaydının (seferin) durumu. */
enum DepartureStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Kayıt açık',
            self::Closed => 'Kayıt kapalı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'warning',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /** Yeni grup kaydı alınabilir mi? */
    public function acceptsGroups(): bool
    {
        return $this === self::Open;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
