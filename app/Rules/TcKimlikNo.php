<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. kimlik numarası: yalnız 11 hane olduğu denetlenir.
 *
 * Kontrol basamağı algoritması BİLEREK uygulanmıyor (kullanıcı isteği): personel
 * deneme verisi ve eksik bilgiyle de kayıt girebilsin. Boşluk, tire gibi ayraçlar
 * kayıtta zaten silinir (Passenger::saving).
 */
class TcKimlikNo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid((string) $value)) {
            $fail('T.C. kimlik numarası 11 haneli olmalıdır.');
        }
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9]{11}$/', preg_replace('/\D/', '', $value) ?? '') === 1;
    }
}
