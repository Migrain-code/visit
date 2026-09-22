<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Ayar okuyucu (spec §5).
 *
 * Öncelik: panel ayarı → config/seo.php varsayılanı.
 * GEÇERSİZ DEĞER FİLTREYİ SESSİZCE KAPATMAZ: aralık dışı veya biçimsiz değer
 * güvenli varsayılana düşer ve bir kez log'lanır.
 */
class SeoConfig
{
    /** Panel ayarları bu önekle saklanır; SiteSettings sayfasıyla çakışmaz. */
    public const PREFIX = 'seo_';

    public static function raw(string $key): mixed
    {
        $value = Setting::allCached()[self::PREFIX.$key] ?? null;

        return ($value === null || $value === '') ? null : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::set(self::PREFIX.$key, is_bool($value) ? ($value ? '1' : '0') : $value);
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $value = self::raw($key);

        return $value !== null ? (string) $value : ($default ?? self::configDefault($key));
    }

    public static function bool(string $key, ?bool $default = null): bool
    {
        $value = self::raw($key);

        if ($value === null) {
            return (bool) ($default ?? self::configDefault($key));
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
    }

    public static function int(string $key, ?int $default = null, ?int $min = null, ?int $max = null): int
    {
        $fallback = (int) ($default ?? self::configDefault($key));
        $value = self::raw($key);

        if ($value === null || ! is_numeric($value)) {
            return $fallback;
        }

        $value = (int) $value;

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            AutomationLog::info('config.out_of_range', ['key' => $key, 'value' => $value, 'fallback' => $fallback]);

            return $fallback;
        }

        return $value;
    }

    /**
     * Eşik okuyucu. Geçersiz eşik (0, 1'den büyük, sayı değil) filtreyi kapatacağı
     * için güvenli varsayılana düşer (spec §5, §9).
     */
    public static function threshold(string $key, ?float $default = null): float
    {
        $fallback = (float) ($default ?? self::configDefault($key));
        $value = self::raw($key);

        if ($value === null || ! is_numeric($value)) {
            return $fallback;
        }

        $value = (float) $value;

        if ($value <= 0.0 || $value > 1.0) {
            AutomationLog::info('config.invalid_threshold', ['key' => $key, 'value' => $value, 'fallback' => $fallback]);

            return $fallback;
        }

        return $value;
    }

    /** seo_duplicate_scan_threshold → config('seo.duplicate.scan_threshold') */
    private static function configDefault(string $key): mixed
    {
        foreach (['duplicate', 'blog', 'internal_links', 'score', 'redirects', 'ai', 'google'] as $group) {
            $prefix = $group.'_';

            if (str_starts_with($key, $prefix)) {
                return config('seo.'.$group.'.'.substr($key, strlen($prefix)));
            }
        }

        return config('seo.'.$key);
    }
}
