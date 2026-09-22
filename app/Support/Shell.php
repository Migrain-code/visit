<?php

namespace App\Support;

/**
 * Kabuk komutu METNİ üretmek için yardımcılar. Hiçbir şey ÇALIŞTIRMAZ.
 *
 * Panel, kullanıcının hosting panelindeki cron'a yapıştıracağı satırları gösterir.
 * escapeshellarg() paylaşımlı hostinglerde sıkça kapatıldığı ve PHP 8'de kapalı
 * fonksiyon çağrısı sayfayı çökerttiği için tırnaklama elle yapılır.
 */
final class Shell
{
    /** Değeri tek tırnakla sarar; escapeshellarg() ile aynı sonucu verir. */
    public static function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
