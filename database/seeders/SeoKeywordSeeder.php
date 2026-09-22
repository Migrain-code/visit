<?php

namespace Database\Seeders;

use App\Models\SeoKeyword;
use App\Support\TurkishText;
use Illuminate\Database\Seeder;

/**
 * Başlangıç kelime havuzu.
 *
 * Bunlar ARAMA HACMİ İDDİASI TAŞIMAZ: hacim alanı boş bırakılır. Gerçek hacim,
 * Search Console bağlandığında seo:discover-keywords ile veriden gelir (spec §10.1).
 */
class SeoKeywordSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            // Ticari niyet — tur sayfalarına atanacak (eşleşen tur varsa). Tur adlarından
            // türeyen kelimeleri ("ayder yaylası turu fiyatları") seo:catalog-keywords üretir.
            ['karadeniz günübirlik turlar', 'transactional', 'COMMERCIAL_PRIMARY', 1],
            ['günübirlik ayder turu', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['günübirlik uzungöl turu fiyatları', 'commercial', 'COMMERCIAL_VARIANT', 2],
            ['kimlikle batum turu', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['pokut yaylası turu fiyatları', 'commercial', 'COMMERCIAL_VARIANT', 2],

            // Bilgi amaçlı — blog yazılarına atanacak
            ['ayder yaylasında ne yapılır', 'informational', 'BLOG_PRIMARY', 1],
            ['ayder yaylasına ne zaman gidilir', 'informational', 'BLOG_PRIMARY', 2],
            ['pokut yaylasına nasıl gidilir', 'informational', 'BLOG_PRIMARY', 1],
            ['huser yaylası gün batımı saati', 'informational', 'BLOG_PRIMARY', 2],
            ['uzungöl\'de ne yapılır', 'informational', 'BLOG_PRIMARY', 2],
            ['uzungöl\'e ne zaman gidilir', 'informational', 'BLOG_SECONDARY', 3],
            ['sümela manastırı giriş saatleri', 'informational', 'BLOG_PRIMARY', 2],
            ['sümela manastırına nasıl çıkılır', 'informational', 'BLOG_PRIMARY', 2],
            ['karaca mağarası nerede', 'informational', 'BLOG_SECONDARY', 3],
            ['zilkale nerede nasıl gidilir', 'informational', 'BLOG_PRIMARY', 2],
            ['palovit şelalesi yürüyüş', 'informational', 'BLOG_SECONDARY', 3],
            ['fırtına vadisi rafting', 'informational', 'BLOG_PRIMARY', 2],
            ['batum\'da gezilecek yerler', 'informational', 'BLOG_PRIMARY', 1],
            ['sarp sınır kapısından kimlikle geçiş', 'informational', 'BLOG_PRIMARY', 1],
            ['batum\'da alışveriş', 'informational', 'BLOG_SECONDARY', 3],
            ['tiflis\'te gezilecek yerler', 'informational', 'BLOG_PRIMARY', 2],
            ['yurt dışı çıkış harcı nasıl ödenir', 'informational', 'BLOG_SECONDARY', 3],
            ['rize\'de ne yenir', 'informational', 'BLOG_PRIMARY', 2],
            ['muhlama nerede yenir', 'informational', 'BLOG_SECONDARY', 3],
            ['hamsiköy sütlacı', 'informational', 'BLOG_SECONDARY', 3],
            ['trabzon\'da bir günde gezilecek yerler', 'informational', 'BLOG_PRIMARY', 2],
            ['karadeniz yayla turunda ne giyilir', 'informational', 'BLOG_PRIMARY', 1],
            ['karadeniz\'e hangi ayda gidilir', 'informational', 'BLOG_PRIMARY', 1],
            ['çocukla karadeniz turu', 'informational', 'BLOG_SUPPORTING', 3],
            ['bulut denizi hangi yaylada görülür', 'informational', 'BLOG_PRIMARY', 2],
        ];

        foreach ($keywords as [$keyword, $intent, $type, $priority]) {
            SeoKeyword::query()->firstOrCreate(
                ['keyword_hash' => md5(TurkishText::lower($keyword))],
                [
                    'keyword' => $keyword,
                    'search_intent' => $intent,
                    'keyword_type' => $type,
                    'priority' => $priority,
                    'status' => true,
                    'note' => 'Başlangıç havuzu',
                ]
            );
        }
    }
}
