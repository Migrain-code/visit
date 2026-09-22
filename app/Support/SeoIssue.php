<?php

namespace App\Support;

/**
 * SEO sorun metinleri — SABİT (spec §3.2, §10.4).
 *
 * Dashboard sayımları bu TAM metinlerle eşleşir. Bir metni değiştirirsen ilgili
 * sayım sessizce sıfırlanır. Metin değiştirmek yerine yeni sabit ekle.
 */
class SeoIssue
{
    public const META_TITLE_MISSING = 'Meta başlık yok';

    public const META_TITLE_TOO_LONG = 'Meta başlık 60 karakterden uzun';

    public const META_TITLE_TOO_SHORT = 'Meta başlık çok kısa (25 karakterden az)';

    public const META_DESC_MISSING = 'Meta açıklama yok';

    public const META_DESC_LENGTH = 'Meta açıklama ideal uzunlukta değil (70-155 karakter)';

    public const H1_MISSING = 'H1 başlık yok';

    public const H1_MULTIPLE = 'Birden fazla H1 başlık var';

    public const CONTENT_TOO_SHORT = 'İçerik çok kısa (300 kelimeden az)';

    public const CONTENT_THIN = 'İçerik zayıf (600 kelimeden az)';

    public const H2_MISSING = 'Ara başlık (H2) yok';

    public const IMAGE_ALT_MISSING = 'Görsellerde ALT metni eksik';

    public const FAQ_MISSING = 'Sık sorulan sorular bölümü yok';

    public const SLUG_NOT_ASCII = 'Slug ASCII değil veya büyük harf içeriyor';

    public const NOT_PUBLISHED = 'Sayfa yayında değil';

    public const IMAGE_MISSING = 'Sayfada görsel yok';

    public const INTERNAL_LINK_MISSING = 'İç link yok';

    public const INTERNAL_LINK_FEW = 'İç link sayısı az (2\'den az)';

    public const EMPTY_LINK = 'Boş veya "#" hedefli bağlantı var';

    public const NOT_INDEXED = 'Google tarafından indekslenmemiş';

    /** Dashboard'da gruplanan tüm sorunlar. @return array<int, string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
