<?php

namespace App\Support;

/**
 * Grafik renk paleti.
 *
 * Değerler doğrulanmıştır (açık ve koyu yüzeyde ayrı ayrı): renk körlüğü ayrımı,
 * parlaklık bandı, doygunluk tabanı ve yüzeye karşı kontrast kontrollerinden geçer.
 * Renk değiştirirken paleti yeniden doğrulamadan dokunmayın.
 *
 * KURAL: kategorik renkler SABİT SIRAYLA atanır, döngüye sokulmaz. Dokuzuncu bir
 * seri üretilmiş bir renk almaz; "Diğer" altında toplanır.
 */
class ChartPalette
{
    /** Kategorik sıra — açık yüzey (#fcfcfb). */
    public const LIGHT = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

    /** Aynı sekiz renk, koyu yüzey (#1a1a19) için adımlanmış hâli. */
    public const DARK = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];

    /** Tek seri için varsayılan (mavi). */
    public const PRIMARY = '#2a78d6';

    public const PRIMARY_DARK = '#3987e5';

    /** Durum renkleri — kategorik seri olarak ASLA kullanılmaz. */
    public const STATUS = [
        'good' => '#1baf7a',
        'warning' => '#eda100',
        'critical' => '#e34948',
        'neutral' => '#8a897f',
    ];

    /**
     * Kategorik dilim listesi; $count sekizi aşarsa fazlası "Diğer"e katlanır.
     *
     * @return array<int, string>
     */
    public static function categorical(int $count): array
    {
        return array_slice(self::LIGHT, 0, min($count, count(self::LIGHT)));
    }

    /** Skora göre durum rengi. */
    public static function forScore(int $score): string
    {
        return match (true) {
            $score >= 80 => self::STATUS['good'],
            $score >= 60 => self::STATUS['warning'],
            default => self::STATUS['critical'],
        };
    }
}
