<?php

namespace App\Support;

/**
 * Türkçeye özgü metin normalleştirme.
 *
 * DİKKAT (spec §7.5): mb_strtolower('İ') → "i" + U+0307 (kombine nokta) üretir ve
 * düz "i" ile EŞLEŞMEZ. Aynı şekilde 'I' harfi Türkçede 'ı' olmalıdır. Bu yüzden
 * büyük harfler ELLE eşlenir, ardından artık kombine nokta silinir.
 */
class TurkishText
{
    /** Türkçe büyük harf → küçük harf eşlemesi (spec §3.4 adım 1). */
    private const UPPER_MAP = [
        'İ' => 'i', 'I' => 'ı', 'Ş' => 'ş', 'Ğ' => 'ğ',
        'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç',
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

    /**
     * ARAMA kelimesi için küçük harf.
     *
     * lower() Türkçe kuralını uygular ve "I" harfini "ı" yapar — Türkçe kelimelerde
     * doğrudur. Ama yabancı bir marka adında yanlıştır: "IKEA" → "ıkea" olur, oysa
     * kimse öyle aramaz. Salt ASCII harflerden oluşan bir ad Türkçe'ye özgü bir
     * harf içermediği için yabancı sayılır ve ASCII kuralıyla küçültülür.
     *
     * Ödün: "ILGI" gibi ASCII yazılmış Türkçe bir kelime "ilgi" olur, "ılgı" değil.
     * Marka ve bölge adlarında bu durum pratikte görülmez; IKEA görülür.
     */
    public static function searchLower(?string $value): string
    {
        $value = (string) $value;

        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return mb_strtolower($value, 'UTF-8');
        }

        return self::lower($value);
    }

    /**
     * Başlığı karşılaştırılabilir token kümesine indirger (spec §3.4).
     *
     * @return array<int, string> benzersiz, sıralı token listesi
     */
    public static function tokens(?string $title, ?int $stemLength = null): array
    {
        $stemLength = $stemLength ?: (int) config('seo.duplicate.stem_length', 6);
        $stopWords = array_map([self::class, 'lower'], (array) config('seo.stop_words', []));

        $text = self::lower($title);

        if ($text === '') {
            return [];
        }

        // 3. harf dışı her şeyi boşluğa çevir (rakamlar da atılır: "POS 2024" ≈ "POS 2025")
        $text = preg_replace('/[^\p{L}]+/u', ' ', $text) ?? '';

        $tokens = [];

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            // 4. 3 harften kısa kelimeleri ve durak kelimeleri at
            if (mb_strlen($word, 'UTF-8') < 3 || in_array($word, $stopWords, true)) {
                continue;
            }

            /*
             * 5. Kaba gövdeleme: ilk N harf ("restoran" ≈ "restoranlar" ≈ "restoranlarda").
             *
             * BİLİNEN SINIR: kökü N harften kısa kelimelerde ek katlanmaz.
             * Örnek: "rayı" (4) ile "raylar" (6) farklı token üretir, çünkü kök "ray" (3)
             * kesme uzunluğunun altındadır. Uzun köklerde (restoran, montaj, gardırop)
             * algoritma doğru çalışır; spec §7.1'deki dört gerçek tekrar çifti %100 yakalanır.
             *
             * Bu sınır bilinçli olarak kabul edilmiştir: daha agresif bir gövdeleyici
             * yanlış eşleşmeleri artırır ve eşik kalibrasyonunu geçersiz kılar. Kısa köklü
             * tekrarlar, çakışma tarama ekranında elle görülür.
             */
            $tokens[] = mb_substr($word, 0, $stemLength, 'UTF-8');
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * İki başlık arasındaki Jaccard benzerliği (0.0 – 1.0).
     *
     * Boş başlık ASLA tekrar sayılmaz → 0.0 döner (spec §9).
     */
    public static function similarity(?string $a, ?string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        if ($union === 0) {
            return 0.0;
        }

        return round($intersection / $union, 4);
    }

    /** ASCII slug (spec §10.1: slug ASCII olmalı). */
    public static function slug(?string $value): string
    {
        $value = self::lower($value);
        $value = strtr($value, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c']);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }
}
