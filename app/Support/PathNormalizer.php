<?php

namespace App\Support;

/**
 * Yol normalleştirme ve hash üretimi.
 *
 * Yönlendirme tablosunun arama anahtarı from_hash'tir; bu sınıf tek doğru kaynaktır
 * (spec §7.10 — hash elle doldurulursa yönlendirme sessizce çalışmaz).
 */
class PathNormalizer
{
    /**
     * Tam URL veya yolu tek biçime indirger: "/a/b" (sorgu ve fragment atılır,
     * sondaki eğik çizgi silinir, küçük harfe çevrilir, kök "/" kalır).
     */
    public static function normalize(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return '/';
        }

        // Tam URL geldiyse yalnız path kısmını al.
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        }

        // Sorgu ve fragment at.
        $path = explode('#', $path, 2)[0];
        $path = explode('?', $path, 2)[0];

        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $path = rawurldecode($path);

        return TurkishText::lower($path) ?: '/';
    }

    /** Normalleştirilmiş yolun md5 hash'i — unique index ve arama için. */
    public static function hash(?string $path): string
    {
        return md5(self::normalize($path));
    }

    /** İki yol arasındaki benzerlik (0.0 – 1.0). Yönlendirme önerisi için (spec §3.8). */
    public static function similarity(string $a, string $b): float
    {
        $a = trim(self::normalize($a), '/');
        $b = trim(self::normalize($b), '/');

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        // Son segment eşleşmesi güçlü bir sinyaldir: /eski/ardesen → /rize/ardesen
        $lastA = substr(strrchr('/'.$a, '/') ?: '', 1);
        $lastB = substr(strrchr('/'.$b, '/') ?: '', 1);

        if ($lastA !== '' && $lastA === $lastB) {
            $percent = max($percent, 88.0);
        }

        return round($percent / 100, 4);
    }
}
