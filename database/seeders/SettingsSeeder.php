<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'Visit Tur',
            'site_tagline' => 'Rize ve Trabzon Çıkışlı Günübirlik Turlar',
            'meta_title' => 'Visit Tur | Rize ve Trabzon Çıkışlı Günübirlik Turlar',
            'meta_description' => 'Rize ve Trabzon çıkışlı günübirlik turlar: Ayder, Uzungöl, Sümela Manastırı, Pokut, Huser, Zilkale ve Batum. Otelinizden alınma, güncel tur takvimi ve kişi başı fiyatlar.',
            'whatsapp_message' => 'Merhaba, {tur} hakkında bilgi almak istiyorum.',
            'working_hours' => 'Pazartesi - Cumartesi: 09:00 - 19:00',
            'address' => 'Rize Merkez, Rize',
            'service_area_text' => 'Rize ve Trabzon',
            'tursab_no' => '',
            'company_title' => '',

            'hero_badge' => 'Rize · Trabzon çıkışlı günübirlik turlar',
            'hero_title' => 'Karadeniz\'in Yaylalarını, Göllerini Birlikte Gezelim',
            'hero_subtitle' => 'Ayder, Uzungöl, Sümela, Pokut, Huser ve Batum. Otelinizden alıyor, rehber eşliğinde gezdirip akşam aynı yere bırakıyoruz.',
            'hero_bullets' => "Otelinizden ya da size en yakın noktadan alınma\nProgram, fiyat ve dahil olanlar baştan net",
            'hero_image' => 'site/hero.webp',

            'about_subtitle' => 'Hakkımızda',
            'about_title' => 'Doğu Karadeniz\'i Bilen Yerel Tur Firması',
            'about_text' => '<p>Rize ve Trabzon\'da konaklayan misafirlerimiz için günübirlik yayla, göl, kültür ve Batum turları düzenliyoruz. Sabah otelinizden ya da size en yakın biniş noktasından alıyor, akşam aynı yere bırakıyoruz.</p><p>Her turun programını, fiyata neyin dahil olup olmadığını ve alınış saatinizi rezervasyon sırasında yazılı olarak bildiririz. Aileler ve arkadaş grupları aynı araçta, yan yana yolculuk eder.</p>',
            'about_image' => 'site/about-1.webp',
            'about_image_2' => 'site/about-2.webp',
            'about_page_content' => '<p>Visit Tur, Doğu Karadeniz\'de günübirlik turlar düzenleyen yerel bir seyahat acentesidir. Rize ve Trabzon\'un il ve ilçelerinden misafir alıyor; Ayder, Pokut, Sal ve Huser yaylalarına, Uzungöl ve Fırtına Vadisi\'ne, Sümela Manastırı\'na ve Sarp Sınır Kapısı üzerinden Batum\'a turlar düzenliyoruz.</p><h2>Nasıl çalışıyoruz?</h2><p>Tur takvimimizden size uyan günü seçersiniz. Telefon, WhatsApp ya da rezervasyon formu üzerinden bize ulaştığınızda kişi sayınızı ve konakladığınız yeri alır, alınış noktanızı ve saatinizi bildiririz. Kayıt sırasında yolcu bilgilerinizi (ad, soyad, kimlik numarası) alırız; bu bilgiler yalnız sigorta ve yolcu listesi için kullanılır.</p><h2>Gruplar birlikte yolculuk eder</h2><p>Aileler ve arkadaş grupları araçlara bölünmeden yerleştirilir. Birden fazla araçla çıktığımız günlerde hangi araçta olduğunuzu kalkıştan önce size bildiririz.</p><h2>Yayla yolları ve hava</h2><p>Yüksek yaylalara çıkan yollar dar ve virajlıdır; bu bölümlerde bölgeye uygun yayla araçları kullanırız. Karadeniz\'de hava yazın bile hızla değişir: yanınıza yağmurluk ve kaymayan bir ayakkabı almanızı öneririz.</p><h2>Neden biz?</h2><ul><li>Bölgeyi, yolları ve mevsimi bilen yerel ekip</li><li>Otelden alınma ve otele bırakılma</li><li>Program ve fiyat baştan net, sonradan ek ücret çıkmaz</li><li>Rezervasyondan dönüşe kadar ulaşabileceğiniz bir ekip</li></ul>',

            // Sayaçlar DOĞRULANABİLİR olmalı: bunlar veritabanındaki kayıt sayılarıdır.
            'stat_1_value' => '2',
            'stat_1_label' => 'Kalkış İli',
            'stat_2_value' => '30',
            'stat_2_label' => 'İlçeden Katılım',
            'stat_3_value' => '9',
            'stat_3_label' => 'Tur Programı',
            'stat_4_value' => 'Rehberli',
            'stat_4_label' => 'Tüm Turlar',

            'process_subtitle' => 'Nasıl Katılırım?',
            'process_title' => 'Dört Adımda Tur Kaydı',
            'process_image' => 'site/process.webp',

            'cta_title' => 'Yarınki tura yerinizi ayırtın',
            'cta_text' => 'Kişi sayınızı ve konakladığınız oteli yazın; aynı gün içinde sizi arayalım.',
            'cta_image' => 'site/cta-bg.webp',
            'banner_image' => 'site/page-banner.webp',

            'footer_text' => 'Rize ve Trabzon çıkışlı günübirlik yayla, göl, kültür ve Batum turları. Otelinizden alıyor, rehber eşliğinde gezdiriyoruz.',

            'facebook_url' => '',
            'instagram_url' => '',
            'youtube_url' => '',
            'map_embed' => '',
            'google_analytics_id' => '',
            'google_site_verification' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flush();
    }
}
