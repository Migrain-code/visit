<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. kimlik numarası doğrulaması (11 hane + iki kontrol basamağı).
 *
 * Yalnız BİÇİMİ doğrular: numaranın gerçekten o kişiye ait olduğunu söylemez (o, NVİ
 * sorgusu gerektirir). Amaç, elle girişte bir hanenin yanlış yazılmasını yakalamaktır;
 * yanlış TC ile kesilen sigorta poliçesi geçersizdir.
 */
class TcKimlikNo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid((string) $value)) {
            $fail('Geçerli bir T.C. kimlik numarası girin (11 hane).');
        }
    }

    public static function isValid(string $value): bool
    {
        if (! preg_match('/^[1-9][0-9]{10}$/', $value)) {
            return false;
        }

        $d = array_map('intval', str_split($value));

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        // PHP'de % negatif sonuç verebilir; +10 ile pozitife çekilir.
        $tenth = ((($odd * 7 - $even) % 10) + 10) % 10;
        $eleventh = (array_sum(array_slice($d, 0, 10))) % 10;

        return $d[9] === $tenth && $d[10] === $eleventh;
    }
}
