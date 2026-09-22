<?php

namespace App\Enums;

/** Grup kaydının durumu. */
enum GroupStatus: string
{
    case Confirmed = 'confirmed';
    case Pending = 'pending';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Kesin kayıt',
            self::Pending => 'Opsiyon',
            self::Cancelled => 'İptal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Pending => 'warning',
            self::Cancelled => 'danger',
        };
    }

    /** Koltuk tutar mı? Opsiyon da koltuk tutar; yalnız iptal edilen tutmaz. */
    public function holdsSeats(): bool
    {
        return $this !== self::Cancelled;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
