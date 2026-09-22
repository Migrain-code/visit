<?php

namespace App\Support;

/**
 * Bot taramalarını 404 günlüğünden eler.
 *
 * Gerekçe (spec §7.13): log'ların %99'u tek bir gürültü kaynağından gelirse gerçek
 * hataları gömer. wp-admin, .env, .php gibi sondajlar günde binlerce satır üretir ve
 * hiçbiri yönlendirilecek gerçek bir sayfa değildir.
 */
class ProbePaths
{
    private const EXTENSIONS = ['.php', '.asp', '.aspx', '.jsp', '.cgi', '.env', '.git', '.sql', '.bak', '.zip', '.tar', '.gz', '.yml', '.ini'];

    private const NEEDLES = ['wp-admin', 'wp-content', 'wp-includes', 'wordpress', 'phpmyadmin', 'xmlrpc', '/vendor/', '/.well-known/traffic-advice', 'autodiscover', 'owa/', 'cgi-bin'];

    public static function isNoise(string $path): bool
    {
        $path = TurkishText::lower('/'.ltrim($path, '/'));

        foreach (self::EXTENSIONS as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }

        foreach (self::NEEDLES as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        return false;
    }
}
