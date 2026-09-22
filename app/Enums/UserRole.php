<?php

namespace App\Enums;

/**
 * Panel rolleri.
 *
 * Tek bir "role" alanı kullanılır; beş rol için ayrı bir yetki paketi kurmak
 * gereksiz karmaşıklık olurdu. Yetkiler App\Policies altındaki ilkelerde tanımlıdır.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Operasyon = 'operasyon';
    case Kayit = 'kayit';
    case Icerik = 'icerik';
    case Rehber = 'rehber';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Süper Yönetici',
            self::Operasyon => 'Operasyon Sorumlusu',
            self::Kayit => 'Kayıt Personeli',
            self::Icerik => 'İçerik Editörü',
            self::Rehber => 'Rehber',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Her şeye erişir: turlar, seferler, yolcular, içerik, SEO, kullanıcılar ve ayarlar.',
            self::Operasyon => 'Tur kayıtlarını açar, araç atar, grupları araçlara dağıtır, turları ve fiyatları yönetir.',
            self::Kayit => 'Grup ve yolcu kaydı girer, rezervasyon taleplerini takip eder. Araç ve sefer tanımlayamaz.',
            self::Icerik => 'Tur sayfaları, blog, galeri ve bölge sayfalarını yönetir. Yolcu verisini göremez.',
            self::Rehber => 'Yalnız rehberi olduğu seferleri ve o seferlerin yolcu listesini görür; değiştiremez.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Operasyon => 'info',
            self::Kayit => 'success',
            self::Icerik => 'warning',
            self::Rehber => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
