<?php

namespace App\Support;

/**
 * Türkçeye özgü metin dönüşümleri.
 *
 * DİKKAT: mb_strtolower('İ') → "i" + U+0307 (kombine nokta) üretir ve düz "i" ile
 * EŞLEŞMEZ; mb_strtoupper('i') ise "I" verir, oysa Türkçede "İ" olmalıdır.
 * Bu yüzden Türkçe harfler ELLE eşlenir, ardından artık kombine nokta silinir.
 */
class TurkishText
{
    /** Türkçe büyük harf → küçük harf eşlemesi. */
    private const UPPER_MAP = [
        'İ' => 'i', 'I' => 'ı', 'Ş' => 'ş', 'Ğ' => 'ğ',
        'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç',
    ];

    /** Türkçe küçük harf → büyük harf eşlemesi. */
    private const LOWER_MAP = [
        'i' => 'İ', 'ı' => 'I', 'ş' => 'Ş', 'ğ' => 'Ğ',
        'ü' => 'Ü', 'ö' => 'Ö', 'ç' => 'Ç',
    ];

    /** U+0307 COMBINING DOT ABOVE — mb_strtolower('İ') sonrası artık kalır. */
    private const COMBINING_DOT = "\u{0307}";

    public static function lower(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $value = strtr($value, self::UPPER_MAP);
        $value = mb_strtolower($value, 'UTF-8');

        return str_replace(self::COMBINING_DOT, '', $value);
    }

    /** "Geziyor" → "GEZİYOR" (mb_strtoupper "GEZIYOR" verirdi). */
    public static function upper(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return mb_strtoupper(strtr($value, self::LOWER_MAP), 'UTF-8');
    }

    /** ASCII slug: dosya adları ve adresler için. */
    public static function slug(?string $value): string
    {
        $value = self::lower($value);
        $value = strtr($value, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c']);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }
}
