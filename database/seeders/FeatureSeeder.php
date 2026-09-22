<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        if (Feature::query()->exists()) {
            return;
        }

        $trust = [
            ['fa-solid fa-hotel', 'Otelden Alınma', 'Sabah otelinizden alıyor, akşam aynı yere bırakıyoruz.'],
            ['fa-solid fa-user-tie', 'Rehberli Turlar', 'Her turda gezilen yerleri anlatan, programı yöneten bir rehber.'],
            ['fa-solid fa-file-signature', 'Net Program ve Fiyat', 'Neyin dahil olduğu kayıttan önce yazılı olarak belli.'],
            ['fa-solid fa-people-group', 'Gruplar Bölünmez', 'Aileniz ve arkadaşlarınızla aynı araçta yolculuk edersiniz.'],
            ['fa-solid fa-shield-heart', 'Seyahat Sigortası', 'Zorunlu seyahat sigortası tüm turlarda fiyata dahil.'],
            ['fa-brands fa-whatsapp', 'Kolay İletişim', 'Kayıttan dönüşe kadar telefon ve WhatsApp\'tan ulaşılabilir ekip.'],
        ];

        $whyUs = [
            ['fa-solid fa-map-location-dot', 'Bölgeyi bilen yerel ekip', 'Yayla yollarını, mevsimi ve en iyi manzara saatini biliyoruz.'],
            ['fa-solid fa-list-check', 'Gün gün yazılı program', 'Hangi gün nereyi gezeceğinizi ve serbest zamanınızı baştan bilirsiniz.'],
            ['fa-solid fa-hand-holding-dollar', 'Sürpriz ücret yok', 'Dahil olan ve olmayan her kalem tur sayfasında açıkça yazar.'],
            ['fa-solid fa-van-shuttle', 'Yola uygun araç', 'Sahilde konforlu minibüs, yayla yolunda bölgeye uygun yayla aracı.'],
            ['fa-solid fa-children', 'Aileler bir arada', 'Gruplar araçlara bölünmeden yerleştirilir; kimse ailesinden ayrı düşmez.'],
            ['fa-regular fa-clock', 'Zamanında alınış', 'Alınış saatiniz ve noktanız bir gün önce size tekrar hatırlatılır.'],
            ['fa-solid fa-headset', 'Tur boyunca destek', 'Rehberiniz ve ofisimiz tur süresince ulaşılabilir durumdadır.'],
        ];

        $process = [
            ['fa-regular fa-calendar-days', 'Turunuzu Seçin', 'Tur takviminden size uyan turu ve günü belirleyin.'],
            ['fa-solid fa-phone', 'Bize Ulaşın', 'Telefon, WhatsApp ya da rezervasyon formuyla kişi sayınızı bildirin.'],
            ['fa-regular fa-id-card', 'Kaydınızı Tamamlayın', 'Yolcu bilgilerinizi ve otelinizi bildirin; alınış saatinizi öğrenin.'],
            ['fa-solid fa-van-shuttle', 'Yola Çıkın', 'Tur sabahı alınış noktanızda olun, gerisini bize bırakın.'],
        ];

        foreach ([Feature::TYPE_TRUST => $trust, Feature::TYPE_WHY_US => $whyUs, Feature::TYPE_PROCESS => $process] as $type => $items) {
            foreach ($items as $i => [$icon, $title, $description]) {
                Feature::query()->create([
                    'type' => $type,
                    'icon' => $icon,
                    'title' => $title,
                    'description' => $description,
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
