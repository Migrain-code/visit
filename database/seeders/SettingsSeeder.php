<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'RTEÜ Geziyor',
            'site_tagline' => 'Keşfet · Tanış · Yaşa',
            'meta_title' => 'RTEÜ Geziyor | Rize Çıkışlı Öğrenci Turları',
            'meta_description' => 'Rize çıkışlı günübirlik öğrenci turları: Batum, Sümela, Ayder, Huser, Pokut ve Uzungöl. Haftalık rotalar, öğrenci dostu fiyatlar, profesyonel rehber.',
            'whatsapp_message' => 'Merhaba, {tur} hakkında bilgi almak istiyorum.',
            'address' => 'Rize',
            'instagram_handle' => 'rteugeziyor',
            'instagram_url' => '',

            'hero_title' => 'Bu Haftanın',
            'hero_highlight' => 'Rotaları',
            'hero_subtitle' => 'Yeni yerler, yeni insanlar, unutulmaz anılar seni bekliyor.',
            'hero_cta_text' => 'Keşfetmeye Başla',
            'hero_note' => 'Daha fazla gör, daha fazla yaşa...',
            'hero_image' => 'site/hero.webp',

            'tours_title' => 'Haftalık Turlar',
            'tours_empty_text' => 'Yeni rotalar çok yakında. Instagram\'dan takipte kal!',

            'feature_1_title' => 'Profesyonel Rehber',
            'feature_1_text' => 'Uzman kadro',
            'feature_2_title' => 'Konforlu Araçlar',
            'feature_2_text' => 'Rahat ve güvenli',
            'feature_3_title' => 'Öğrenci Dostu Fiyat',
            'feature_3_text' => 'En iyi deneyim',
            'feature_4_title' => 'Güvenli Organizasyon',
            'feature_4_text' => 'Daima yanındayız',

            'google_analytics_id' => '',
            'google_site_verification' => '',
            'map_embed' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flush();
    }
}
