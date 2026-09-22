<?php

namespace App\Support;

/**
 * public/storage bağlantısı: yüklenen görsellerin sitede görünmesini sağlar.
 *
 * Laravel bağlantıyı PHP'nin symlink() fonksiyonuyla, o yoksa exec() ile kabuktan
 * kurar. Bazı hostingler İKİSİNİ de kapatır; o zaman bağlantı PHP'den kurulamaz.
 * Cron ise PHP'den geçmeden doğrudan kabuk komutu çalıştırır: bağlantı tek seferlik
 * bir cron göreviyle kurulabilir.
 */
class StorageLink
{
    public function linkPath(): string
    {
        return public_path('storage');
    }

    public function targetPath(): string
    {
        return storage_path('app/public');
    }

    public function exists(): bool
    {
        $link = $this->linkPath();

        return is_link($link) || file_exists($link);
    }

    public function canCreateFromPhp(): bool
    {
        return function_exists('symlink') || function_exists('exec');
    }

    /** Hosting panelindeki cron'a yapıştırılacak tek satır. Bağlantı varsa hiçbir şey yapmaz. */
    public function cronCommand(): string
    {
        $link = Shell::quote($this->linkPath());
        $target = Shell::quote($this->targetPath());

        return "[ -e {$link} ] || ln -s {$target} {$link}";
    }
}
